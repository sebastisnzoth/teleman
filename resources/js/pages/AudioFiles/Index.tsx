import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useForm, router, Head } from '@inertiajs/react';
import { Upload, FileAudio, Trash2, Play, Pause, Clock, HardDrive, AlertCircle } from 'lucide-react';
import { FormEventHandler, useState, useRef } from 'react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { PageHelp } from '@/components/page-help';

interface AudioFile {
  id: number;
  filename: string;
  file_size: number;
  duration: number;
  mime_type: string;
  description: string | null;
  created_at: string;
}

interface Props {
  audioFiles?: {
    data: AudioFile[];
    links: unknown[];
    meta: {
      current_page: number;
      from: number | null;
      last_page: number;
      per_page: number;
      to: number | null;
      total: number;
    };
  };
}

export default function AudioFilesIndex({ audioFiles = { data: [], links: [], meta: { current_page: 1, from: null, last_page: 1, per_page: 15, to: null, total: 0 } } }: Props) {
  const { t } = useTranslation();
  const [isDragging, setIsDragging] = useState(false);
  const [playingId, setPlayingId] = useState<number | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const audioRefs = useRef<Record<number, HTMLAudioElement>>({});

  const { data, setData, post, processing, errors, reset } = useForm<{
    file: File | null;
    description: string;
  }>({
    file: null,
    description: '',
  });

  const handleDragOver = (e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = (e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    setIsDragging(false);
  };

  const handleDrop = (e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    setIsDragging(false);

    const files = e.dataTransfer.files;
    if (files.length > 0) {
      const file = files[0];
      if (file.type.startsWith('audio/')) {
        setData('file', file);
      } else {
        alert('Please upload an audio file (MP3 or WAV)');
      }
    }
  };

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (files && files.length > 0) {
      setData('file', files[0]);
    }
  };

  const handleSubmit: FormEventHandler = (e) => {
    e.preventDefault();

    if (!data.file) {
      return;
    }

    post('/audio-files', {
      preserveScroll: true,
      onSuccess: () => {
        reset();
        if (fileInputRef.current) {
          fileInputRef.current.value = '';
        }
      },
    });
  };

  const handleBrowseClick = () => {
    fileInputRef.current?.click();
  };

  const handleDelete = (id: number, filename: string) => {
    if (confirm(`Are you sure you want to delete "${filename}"?`)) {
      router.delete(`/audio-files/${id}`);
    }
  };

  const handlePlayPause = (audioFile: AudioFile) => {
    const audioElement = audioRefs.current[audioFile.id];

    if (!audioElement) {
      // Create audio element
      const audio = new Audio(`/audio-files/${audioFile.id}/stream`);
      audioRefs.current[audioFile.id] = audio;

      audio.onended = () => {
        setPlayingId(null);
      };

      audio.play();
      setPlayingId(audioFile.id);
    } else {
      if (playingId === audioFile.id) {
        audioElement.pause();
        setPlayingId(null);
      } else {
        audioElement.play();
        setPlayingId(audioFile.id);
      }
    }
  };

  const formatFileSize = (bytes: number) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  };

  const formatDuration = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;
  };

  const breadcrumbs: BreadcrumbItem[] = [
    {
      title: t('common.dashboard'), href: dashboard().url,
    },
    {
      title: t('audio_files.audio_files'), href: '/audio-files',
    },
  ];

  const helpSections = [
    {
      title: 'Audio File Management',
      content: 'Upload and manage audio files used in voice-to-voice campaigns. These files are played during automated calls to deliver your message.',
    },
    {
      title: 'Supported Formats',
      content: 'Upload MP3 or WAV audio files. Maximum file size is 10MB. Ensure your audio is clear and properly recorded for best call quality.',
    },
    {
      title: 'Uploading Files',
      content: 'Drag and drop audio files into the upload zone or click "Browse Files" to select from your computer. Add an optional description to help identify the file\'s purpose.',
    },
    {
      title: 'Playing & Previewing',
      content: 'Click the play button next to any audio file to preview it. This helps verify the audio before using it in a campaign.',
    },
    {
      title: 'Using in Campaigns',
      content: 'After uploading, select these audio files when creating a voice-to-voice campaign. The file will be played when the recipient answers the call.',
    },
    {
      title: 'File Information',
      content: 'View file details including size, duration, and upload date. This helps manage storage and select appropriate files for campaigns.',
    },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('audio_files.audio_files')} />
      <div className="mx-auto max-w-6xl">
        <div className="flex items-center justify-between mb-6">
          <Heading title={t('audio_files.audio_files')} description={t('audio_files.manage_your_voice_message_recordings')} />
          <PageHelp title={t('audio_files.audio_files_help')} sections={helpSections} />
        </div>

        {/* Upload Card */}
        <Card>
          <CardHeader>
            <CardTitle>{t('audio_files.upload_audio_file')}</CardTitle>
            <CardDescription>
              Upload MP3 or WAV files for voice-to-voice campaigns. Maximum file size: 10MB
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4">
              {/* Upload Zone */}
              <div
                onDragOver={handleDragOver}
                onDragLeave={handleDragLeave}
                onDrop={handleDrop}
                className={`relative rounded-lg border-2 border-dashed p-8 text-center transition-colors ${
                  isDragging
                    ? 'border-slate-400 bg-slate-50'
                    : 'border-slate-200 hover:border-slate-300'
                }`}
              >
                <input
                  ref={fileInputRef}
                  type="file"
                  accept="audio/mp3,audio/mpeg,audio/wav"
                  onChange={handleFileSelect}
                  className="hidden"
                />

                <div className="flex flex-col items-center gap-2">
                  <div className="rounded-full bg-slate-100 p-3">
                    <Upload className="h-6 w-6 text-slate-600" />
                  </div>

                  {data.file ? (
                    <div className="flex items-center gap-2">
                      <FileAudio className="h-4 w-4 text-slate-600" />
                      <span className="text-sm font-medium">{data.file.name}</span>
                      <span className="text-xs text-slate-500">
                        ({formatFileSize(data.file.size)})
                      </span>
                    </div>
                  ) : (
                    <>
                      <p className="text-sm font-medium">
                        Drag and drop your audio file here, or{' '}
                        <button
                          type="button"
                          onClick={handleBrowseClick}
                          className="text-slate-900 underline hover:text-slate-700"
                        >
                          browse
                        </button>
                      </p>
                      <p className="text-xs text-slate-500">MP3 or WAV files up to 10MB</p>
                    </>
                  )}
                </div>

                {errors.file && (
                  <Alert variant="destructive" className="mt-4">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>{errors.file}</AlertDescription>
                  </Alert>
                )}
              </div>

              {/* Description Input */}
              <div>
                <label htmlFor="description" className="block text-sm font-medium text-slate-700">
                  Description (optional)
                </label>
                <textarea
                  id="description"
                  value={data.description}
                  onChange={(e) => setData('description', e.target.value)}
                  placeholder="Brief description of this audio file..."
                  rows={2}
                  className="mt-1.5 w-full rounded-md border border-slate-200 px-3 py-2 text-sm focus:border-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400"
                />
              </div>

              {/* Actions */}
              {data.file && (
                <div className="flex justify-end gap-2">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => {
                      reset();
                      if (fileInputRef.current) {
                        fileInputRef.current.value = '';
                      }
                    }}
                    disabled={processing}
                  >
                    Cancel
                  </Button>
                  <Button type="submit" disabled={processing}>
                    {processing ? 'Uploading...' : 'Upload Audio'}
                  </Button>
                </div>
              )}
            </form>
          </CardContent>
        </Card>

        {/* Audio Files List */}
        <Card className="mt-6">
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle>{t('audio_files.your_audio_files')}</CardTitle>
              <CardDescription>{audioFiles?.meta?.total ?? 0} files</CardDescription>
            </div>
          </CardHeader>
          <CardContent>
            {audioFiles?.data && audioFiles.data.length > 0 ? (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Filename</TableHead>
                    <TableHead>Description</TableHead>
                    <TableHead>Duration</TableHead>
                    <TableHead>Size</TableHead>
                    <TableHead>Uploaded</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {audioFiles.data.map((audioFile) => (
                    <TableRow key={audioFile.id}>
                      <TableCell className="font-medium">
                        <div className="flex items-center gap-2">
                          <FileAudio className="h-4 w-4 text-slate-500" />
                          {audioFile.filename}
                        </div>
                      </TableCell>
                      <TableCell className="text-slate-600">
                        {audioFile.description || '-'}
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center gap-1 text-slate-600">
                          <Clock className="h-3.5 w-3.5" />
                          {formatDuration(audioFile.duration)}
                        </div>
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center gap-1 text-slate-600">
                          <HardDrive className="h-3.5 w-3.5" />
                          {formatFileSize(audioFile.file_size)}
                        </div>
                      </TableCell>
                      <TableCell className="text-slate-600">
                        {new Date(audioFile.created_at).toLocaleDateString()}
                      </TableCell>
                      <TableCell className="text-right">
                        <div className="flex justify-end gap-2">
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handlePlayPause(audioFile)}
                          >
                            {playingId === audioFile.id ? (
                              <Pause className="h-3.5 w-3.5" />
                            ) : (
                              <Play className="h-3.5 w-3.5" />
                            )}
                          </Button>
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleDelete(audioFile.id, audioFile.filename)}
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            ) : (
              <div className="py-12 text-center">
                <FileAudio className="mx-auto h-12 w-12 text-slate-300" />
                <h3 className="mt-4 text-sm font-semibold">No audio files</h3>
                <p className="mt-2 text-sm text-slate-500">
                  Upload your first audio file to get started
                </p>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
