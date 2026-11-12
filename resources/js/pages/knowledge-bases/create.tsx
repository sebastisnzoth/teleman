import { useState, useRef } from 'react';
import { ArrowLeft, FileText, Link2, Type, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import axios from 'axios';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { toast } from 'sonner';
import { useTranslation } from 'react-i18next';

type InputMode = 'plain' | 'file' | 'url';

const ACCEPTED_FILE_TYPES = '.txt,.md,.csv,.html,text/plain,text/csv,text/markdown,text/html';

export default function KnowledgeBaseCreate() {
  const { t } = useTranslation();
  const [name, setName] = useState('');
  const [content, setContent] = useState('');
  const [inputMode, setInputMode] = useState<InputMode>('plain');
  const [url, setUrl] = useState('');
  const [fetchingUrl, setFetchingUrl] = useState(false);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [saving, setSaving] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard().url },
    { title: t('nav.conversational_ai'), href: '/ai-agents' },
    { title: t('knowledge_base.title'), href: '/knowledge-bases' },
    { title: t('common.create') },
  ];

  const handleFetchUrl = async () => {
    if (!url.trim()) {
      toast.error(t('knowledge_base.enter_url'));
      return;
    }
    setFetchingUrl(true);
    try {
      const res = await axios.post<{ content: string }>('/api/v1/knowledge-bases/fetch-url', { url: url.trim() });
      setContent(res.data.content ?? '');
      toast.success(t('knowledge_base.content_fetched'));
    } catch (err: unknown) {
      const e = err as { response?: { data?: { error?: string; message?: string } } };
      toast.error(e.response?.data?.error ?? e.response?.data?.message ?? t('knowledge_base.fetch_url_failed'));
    } finally {
      setFetchingUrl(false);
    }
  };

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setSelectedFile(file);
    const reader = new FileReader();
    reader.onload = () => setContent(String(reader.result ?? ''));
    reader.readAsText(file, 'UTF-8');
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) {
      toast.error(t('knowledge_base.name_required'));
      return;
    }
    setSaving(true);
    try {
      if (inputMode === 'file' && selectedFile) {
        const formData = new FormData();
        formData.append('name', name.trim());
        formData.append('file', selectedFile);
        await axios.post('/api/v1/knowledge-bases', formData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
      } else {
        await axios.post('/api/v1/knowledge-bases', {
          name: name.trim(),
          content: content.trim() || null,
        });
      }
      toast.success(t('knowledge_base.created'));
      router.visit('/knowledge-bases');
    } catch (error: unknown) {
      const err = error as { response?: { data?: { errors?: Record<string, string[]> } } };
      const msg = err.response?.data?.errors
        ? Object.values(err.response.data.errors).flat().join(' ')
        : t('knowledge_base.create_failed');
      toast.error(msg);
    } finally {
      setSaving(false);
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('knowledge_base.create_title')} />
      <div className="flex h-full flex-1 flex-col gap-4 p-4 md:gap-8 md:p-8">
        <div className="flex items-center gap-4">
          <Button variant="ghost" size="icon" onClick={() => router.visit('/knowledge-bases')}>
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <Heading
            title={t('knowledge_base.create_title')}
            description={t('knowledge_base.create_description')}
          />
        </div>

        <form onSubmit={handleSubmit}>
          <Card>
            <CardHeader>
              <CardTitle>{t('common.details')}</CardTitle>
              <CardDescription>
                {t('knowledge_base.details_description')}
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="name">{t('common.name')} *</Label>
                <Input
                  id="name"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder={t('knowledge_base.name_placeholder')}
                />
              </div>

              <Tabs
                value={inputMode}
                onValueChange={(v) => setInputMode(v as InputMode)}
                className="w-full"
              >
                <TabsList className="grid w-full grid-cols-3">
                  <TabsTrigger value="plain" className="flex items-center gap-2">
                    <Type className="h-4 w-4" />
                    {t('knowledge_base.tab_plain')}
                  </TabsTrigger>
                  <TabsTrigger value="file" className="flex items-center gap-2">
                    <FileText className="h-4 w-4" />
                    {t('knowledge_base.tab_file')}
                  </TabsTrigger>
                  <TabsTrigger value="url" className="flex items-center gap-2">
                    <Link2 className="h-4 w-4" />
                    {t('knowledge_base.tab_url')}
                  </TabsTrigger>
                </TabsList>

                <TabsContent value="plain" className="space-y-2 mt-4">
                  <Label htmlFor="content">{t('knowledge_base.content_label')}</Label>
                  <Textarea
                    id="content"
                    value={content}
                    onChange={(e) => setContent(e.target.value)}
                    placeholder={t('knowledge_base.content_placeholder')}
                    rows={12}
                    className="resize-y"
                  />
                </TabsContent>

                <TabsContent value="file" className="space-y-2 mt-4">
                  <Label>{t('knowledge_base.upload_file')}</Label>
                  <p className="text-sm text-muted-foreground mb-2">
                    {t('knowledge_base.file_supported')}
                  </p>
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept={ACCEPTED_FILE_TYPES}
                    onChange={handleFileSelect}
                    className="block w-full text-sm text-muted-foreground file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-primary file:text-primary-foreground"
                  />
                  {selectedFile && (
                    <p className="text-sm text-muted-foreground">
                      {t('knowledge_base.file_selected', { name: selectedFile.name, size: (selectedFile.size / 1024).toFixed(1) })}
                    </p>
                  )}
                  {content && (
                    <div className="space-y-2 mt-2">
                      <Label>{t('knowledge_base.preview_edit')}</Label>
                      <Textarea
                        value={content}
                        onChange={(e) => setContent(e.target.value)}
                        rows={8}
                        className="resize-y"
                      />
                    </div>
                  )}
                </TabsContent>

                <TabsContent value="url" className="space-y-2 mt-4">
                  <Label htmlFor="url">{t('knowledge_base.import_from_url')}</Label>
                  <p className="text-sm text-muted-foreground mb-2">
                    {t('knowledge_base.url_description')}
                  </p>
                  <div className="flex gap-2">
                    <Input
                      id="url"
                      type="url"
                      value={url}
                      onChange={(e) => setUrl(e.target.value)}
                      placeholder="https://example.com/page"
                    />
                    <Button
                      type="button"
                      variant="outline"
                      onClick={handleFetchUrl}
                      disabled={fetchingUrl || !url.trim()}
                    >
                      {fetchingUrl ? <Loader2 className="h-4 w-4 animate-spin" /> : t('knowledge_base.fetch')}
                    </Button>
                  </div>
                  {content && (
                    <div className="space-y-2 mt-2">
                      <Label>{t('knowledge_base.fetched_content')}</Label>
                      <Textarea
                        value={content}
                        onChange={(e) => setContent(e.target.value)}
                        rows={10}
                        className="resize-y"
                      />
                    </div>
                  )}
                </TabsContent>
              </Tabs>

              <div className="flex gap-2 pt-4">
                <Button type="submit" disabled={saving}>
                  {saving ? t('knowledge_base.creating') : t('common.create')}
                </Button>
                <Button type="button" variant="outline" onClick={() => router.visit('/knowledge-bases')}>
                  {t('common.cancel')}
                </Button>
              </div>
            </CardContent>
          </Card>
        </form>
      </div>
    </AppLayout>
  );
}
