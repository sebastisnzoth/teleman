import { BreadcrumbItem } from '@/types';
import { useTranslation } from 'react-i18next';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import HeadingSmall from '@/components/heading-small';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Switch } from '@/components/ui/switch';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Key, Copy, Trash2, Plus, AlertCircle, Eye, EyeOff, Code } from 'lucide-react';
import { toast } from 'sonner';

interface ApiKey {
  id: number;
  name: string;
  key: string;
  masked_key: string;
  type: 'live' | 'test';
  is_active: boolean;
  allowed_domains: string[];
  permissions: string[];
  last_used_at: string | null;
  created_at: string;
  expires_at: string | null;
}

interface Props {
  apiKeys: ApiKey[];
}

export default function ApiKeysIndex({ apiKeys }: Props) {
  const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
  {
    title: t('settings.title'), href: '/settings/profile',
  },
  {
    title: t('settings.api_keys'), href: '/settings/api-keys',
  },
];
  const [showCreateForm, setShowCreateForm] = useState(false);
  const [formData, setFormData] = useState({
    name: '',
    type: 'live' as 'live' | 'test',
    allowed_domains: '',
  });
  const [revealedKeys, setRevealedKeys] = useState<Set<number>>(new Set());
  const [showIntegrationGuide, setShowIntegrationGuide] = useState(false);

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault();
    router.post('/settings/api-keys', {
      name: formData.name,
      type: formData.type,
      allowed_domains: formData.allowed_domains.split('\n').filter(d => d.trim()),
    }, {
      onSuccess: () => {
        setFormData({ name: '', type: 'live', allowed_domains: '' });
        setShowCreateForm(false);
        toast.success('API key created successfully!');
      },
      onError: (errors) => {
        toast.error(Object.values(errors)[0] as string);
      },
    });
  };

  const handleDelete = (id: number) => {
    if (confirm('Are you sure you want to delete this API key? This action cannot be undone.')) {
      router.delete(`/settings/api-keys/${id}`, {
        onSuccess: () => toast.success('API key deleted'),
        onError: () => toast.error('Failed to delete API key'),
      });
    }
  };

  const handleToggle = (id: number) => {
    router.post(`/settings/api-keys/${id}/toggle`, {}, {
      onSuccess: () => toast.success('API key status updated'),
      onError: () => toast.error('Failed to update API key'),
    });
  };

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    toast.success('Copied to clipboard!');
  };

  const toggleReveal = (id: number) => {
    const newRevealed = new Set(revealedKeys);
    if (newRevealed.has(id)) {
      newRevealed.delete(id);
    } else {
      newRevealed.add(id);
    }
    setRevealedKeys(newRevealed);
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('settings.api_keys')} />
      
      <SettingsLayout>
        <div className="space-y-6 p-6">
          {/* Header */}
          <div className="flex items-start justify-between">
            <HeadingSmall
              title={t('settings.api_keys')}
              description={t('settings.manage_api_keys_to_embed_the_softphone_widget_on_e')}
            />
          </div>

        {/* Integration Guide Button */}
        <div className="mb-6">
          <Button
            variant="outline"
            onClick={() => setShowIntegrationGuide(!showIntegrationGuide)}
          >
            <Code className="mr-2 h-4 w-4" />
            {showIntegrationGuide ? 'Hide' : 'Show'} Integration Guide
          </Button>
        </div>

        {/* Integration Guide */}
        {showIntegrationGuide && (
          <Card className="mb-6 border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/30">
            <CardHeader>
              <CardTitle className="text-blue-900 dark:text-blue-100">{t('settings._embed_softphone_on_your_website')}</CardTitle>
              <CardDescription>
                Add this code to any website to embed the softphone widget
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="rounded-lg bg-slate-900 p-4 font-mono text-sm text-green-400">
                <code>
                  {`<!-- Add before </body> tag -->\n<link rel="stylesheet" href="${window.location.origin}/widget/softphone.css">\n<script src="${window.location.origin}/widget/softphone.umd.js"></script>\n<script>\n  TelemanSoftphone.init({\n    apiKey: 'YOUR_API_KEY_HERE',\n    position: 'bottom-right',\n    theme: 'auto'\n  });\n</script>`}
                </code>
              </div>
              <Alert>
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>
                  <strong>Security Tip:</strong> Only use public keys (pk_live_ or pk_test_) on your frontend.
                  Add domain whitelisting for extra security.
                </AlertDescription>
              </Alert>
            </CardContent>
          </Card>
        )}

        {/* Create New Key Button */}
        {!showCreateForm && (
          <div className="mb-6">
            <Button onClick={() => setShowCreateForm(true)}>
              <Plus className="mr-2 h-4 w-4" />
              Create New API Key
            </Button>
          </div>
        )}

        {/* Create Form */}
        {showCreateForm && (
          <Card className="mb-6">
            <CardHeader>
              <CardTitle>{t('settings.create_new_api_key')}</CardTitle>
              <CardDescription>Generate a new API key for widget integration</CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleCreate} className="space-y-4">
                <div>
                  <Label htmlFor="name">Name</Label>
                  <Input
                    id="name"
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    placeholder="e.g., My Website Widget"
                    required
                  />
                </div>

                <div>
                  <Label>Environment</Label>
                  <div className="flex gap-4 mt-2">
                    <label className="flex items-center gap-2 cursor-pointer">
                      <input
                        type="radio"
                        value="test"
                        checked={formData.type === 'test'}
                        onChange={(e) => setFormData({ ...formData, type: e.target.value as 'test' })}
                      />
                      <span>Test (pk_test_)</span>
                    </label>
                    <label className="flex items-center gap-2 cursor-pointer">
                      <input
                        type="radio"
                        value="live"
                        checked={formData.type === 'live'}
                        onChange={(e) => setFormData({ ...formData, type: e.target.value as 'live' })}
                      />
                      <span>Live (pk_live_)</span>
                    </label>
                  </div>
                </div>

                <div>
                  <Label htmlFor="domains">Allowed Domains (Optional)</Label>
                  <textarea
                    id="domains"
                    value={formData.allowed_domains}
                    onChange={(e) => setFormData({ ...formData, allowed_domains: e.target.value })}
                    placeholder="example.com&#10;app.example.com&#10;(one per line)"
                    className="w-full min-h-[100px] p-2 border rounded-md"
                  />
                  <p className="text-sm text-muted-foreground mt-1">
                    Leave empty to allow all domains (not recommended for production)
                  </p>
                </div>

                <div className="flex gap-2">
                  <Button type="submit">
                    <Plus className="mr-2 h-4 w-4" />
                    Create API Key
                  </Button>
                  <Button type="button" variant="outline" onClick={() => setShowCreateForm(false)}>
                    Cancel
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        )}

        {/* API Keys List */}
        <div className="space-y-4">
          {apiKeys.length === 0 ? (
            <Card>
              <CardContent className="flex flex-col items-center justify-center py-12">
                <Key className="h-12 w-12 text-muted-foreground mb-4" />
                <p className="text-lg font-semibold mb-2">No API Keys Yet</p>
                <p className="text-muted-foreground text-center mb-4">
                  Create your first API key to start embedding the softphone widget on external websites
                </p>
                <Button onClick={() => setShowCreateForm(true)}>
                  <Plus className="mr-2 h-4 w-4" />
                  Create First API Key
                </Button>
              </CardContent>
            </Card>
          ) : (
            apiKeys.map((apiKey) => (
              <Card key={apiKey.id}>
                <CardHeader>
                  <div className="flex items-start justify-between">
                    <div className="flex-1">
                      <div className="flex items-center gap-2 mb-2">
                        <CardTitle className="text-lg">{apiKey.name}</CardTitle>
                        <Badge variant={apiKey.type === 'live' ? 'default' : 'secondary'}>
                          {apiKey.type}
                        </Badge>
                        <Badge variant={apiKey.is_active ? 'default' : 'destructive'}>
                          {apiKey.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                      </div>
                      <CardDescription>
                        Created {apiKey.created_at}
                        {apiKey.last_used_at && ` • Last used ${apiKey.last_used_at}`}
                      </CardDescription>
                    </div>
                    <div className="flex items-center gap-2">
                      <Switch
                        checked={apiKey.is_active}
                        onCheckedChange={() => handleToggle(apiKey.id)}
                      />
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => handleDelete(apiKey.id)}
                      >
                        <Trash2 className="h-4 w-4 text-destructive" />
                      </Button>
                    </div>
                  </div>
                </CardHeader>
                <CardContent className="space-y-3">
                  {/* API Key */}
                  <div>
                    <Label className="text-xs">API Key</Label>
                    <div className="flex items-center gap-2 mt-1">
                      <code className="flex-1 px-3 py-2 bg-muted rounded-md font-mono text-sm">
                        {revealedKeys.has(apiKey.id) ? apiKey.key : apiKey.masked_key}
                      </code>
                      <Button
                        variant="outline"
                        size="icon"
                        onClick={() => toggleReveal(apiKey.id)}
                      >
                        {revealedKeys.has(apiKey.id) ? (
                          <EyeOff className="h-4 w-4" />
                        ) : (
                          <Eye className="h-4 w-4" />
                        )}
                      </Button>
                      <Button
                        variant="outline"
                        size="icon"
                        onClick={() => copyToClipboard(apiKey.key)}
                      >
                        <Copy className="h-4 w-4" />
                      </Button>
                    </div>
                  </div>

                  {/* Allowed Domains */}
                  {apiKey.allowed_domains.length > 0 && (
                    <div>
                      <Label className="text-xs">Allowed Domains</Label>
                      <div className="flex flex-wrap gap-2 mt-1">
                        {apiKey.allowed_domains.map((domain, idx) => (
                          <Badge key={idx} variant="outline">
                            {domain}
                          </Badge>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Quick Copy Integration Code */}
                  <Button
                    variant="outline"
                    size="sm"
                    className="w-full"
                    onClick={() => {
                      const code = `<link rel="stylesheet" href="${window.location.origin}/widget/softphone.css">\n<script src="${window.location.origin}/widget/softphone.umd.js"></script>\n<script>\n  TelemanSoftphone.init({\n    apiKey: '${apiKey.key}',\n    position: 'bottom-right',\n    theme: 'auto'\n  });\n</script>`;
                      copyToClipboard(code);
                    }}
                  >
                    <Code className="mr-2 h-4 w-4" />
                    Copy Integration Code
                  </Button>
                </CardContent>
              </Card>
            ))
          )}
        </div>
      </div>
      </SettingsLayout>
    </AppLayout>
  );
}