import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useForm } from '@inertiajs/react';
import { Upload, FileText, Download, CheckCircle2, XCircle, AlertCircle } from 'lucide-react';
import { FormEventHandler, useState, useRef } from 'react';

interface CSVUploadZoneProps {
  campaignId: number;
  onUploadSuccess?: (result: UploadResult) => void;
}

interface UploadResult {
  imported: number;
  skipped: number;
  errors: Array<{
    row: number;
    phone_number: string;
    error: string;
  }>;
}

export default function CSVUploadZone({ campaignId, onUploadSuccess }: CSVUploadZoneProps) {
  const [isDragging, setIsDragging] = useState(false);
  const [uploadResult, setUploadResult] = useState<UploadResult | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const { data, setData, post, processing, errors, reset } = useForm<{ file: File | null }>({
    file: null,
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
      if (file.type === 'text/csv' || file.name.endsWith('.csv')) {
        setData('file', file);
      } else {
        alert('Please upload a CSV file');
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

    post(`/campaigns/${campaignId}/contacts/upload`, {
      preserveScroll: true,
      onSuccess: (page) => {
        const result = (page.props as { upload_result?: UploadResult }).upload_result;
        if (result) {
          setUploadResult(result);
          if (onUploadSuccess) {
            onUploadSuccess(result);
          }
        }
        reset();
        if (fileInputRef.current) {
          fileInputRef.current.value = '';
        }
      },
      onError: () => {
        setUploadResult(null);
      },
    });
  };

  const handleBrowseClick = () => {
    fileInputRef.current?.click();
  };

  const handleDownloadTemplate = () => {
    window.location.href = '/sample-contacts.csv';
  };

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle>Upload Contacts</CardTitle>
          <CardDescription>
            Import contacts from a CSV file. Maximum 10,000 contacts per upload.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {/* Download Template Button */}
          <div>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={handleDownloadTemplate}
            >
              <Download className="mr-2 h-4 w-4" />
              Download Sample CSV Template
            </Button>
          </div>

          {/* Upload Zone */}
          <form onSubmit={handleSubmit}>
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
                accept=".csv"
                onChange={handleFileSelect}
                className="hidden"
              />

              <div className="flex flex-col items-center gap-2">
                <div className="rounded-full bg-slate-100 p-3">
                  <Upload className="h-6 w-6 text-slate-600" />
                </div>

                {data.file ? (
                  <div className="flex items-center gap-2">
                    <FileText className="h-4 w-4 text-slate-600" />
                    <span className="text-sm font-medium">{data.file.name}</span>
                    <span className="text-xs text-slate-500">
                      ({(data.file.size / 1024).toFixed(1)} KB)
                    </span>
                  </div>
                ) : (
                  <>
                    <p className="text-sm font-medium">
                      Drag and drop your CSV file here, or{' '}
                      <button
                        type="button"
                        onClick={handleBrowseClick}
                        className="text-slate-900 underline hover:text-slate-700"
                      >
                        browse
                      </button>
                    </p>
                    <p className="text-xs text-slate-500">CSV files up to 5MB</p>
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

            {data.file && (
              <div className="mt-4 flex justify-end gap-2">
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
                  {processing ? 'Uploading...' : 'Upload Contacts'}
                </Button>
              </div>
            )}
          </form>
        </CardContent>
      </Card>

      {/* Upload Results */}
      {uploadResult && (
        <Card>
          <CardHeader>
            <CardTitle>Upload Results</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center gap-4">
              <div className="flex items-center gap-2">
                <CheckCircle2 className="h-5 w-5 text-green-600" />
                <span className="text-sm font-medium">
                  {uploadResult.imported} imported
                </span>
              </div>
              {uploadResult.skipped > 0 && (
                <div className="flex items-center gap-2">
                  <XCircle className="h-5 w-5 text-amber-600" />
                  <span className="text-sm font-medium">
                    {uploadResult.skipped} skipped
                  </span>
                </div>
              )}
            </div>

            {uploadResult.errors.length > 0 && (
              <div className="rounded-md bg-amber-50 p-3">
                <h4 className="mb-2 text-sm font-semibold text-amber-900">
                  Skipped Contacts ({uploadResult.errors.length})
                </h4>
                <div className="max-h-64 space-y-2 overflow-y-auto text-xs">
                  {uploadResult.errors.map((error, index) => (
                    <div
                      key={index}
                      className="flex items-start gap-2 rounded bg-white p-2"
                    >
                      <span className="font-mono text-slate-500">Row {error.row}:</span>
                      <span className="font-mono">{error.phone_number}</span>
                      <span className="text-slate-600">- {error.error}</span>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      )}
    </div>
  );
}
