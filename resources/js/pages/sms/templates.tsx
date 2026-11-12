import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { MessageSquare, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

export default function SmsTemplates() {
  const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
  {
    title: t('sms.home'), href: dashboard().url,
  },
  {
    title: t('sms.title'), href: '/sms',
  },
  {
    title: 'Templates',
  },
];
  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('sms.sms_templates')} />

      <div className="container mx-auto p-6 space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight text-foreground">SMS Templates</h1>
            <p className="text-muted-foreground mt-1">
              Create and manage reusable SMS message templates
            </p>
          </div>
          <Button>
            <Plus className="mr-2 h-4 w-4" />
            Create Template
          </Button>
        </div>

        <Card className="border-border bg-card">
          <CardContent className="flex flex-col items-center justify-center py-12">
            <MessageSquare className="h-12 w-12 text-muted-foreground mb-4" />
            <h3 className="text-lg font-semibold text-foreground mb-2">No Templates Yet</h3>
            <p className="text-muted-foreground text-center mb-4">
              Create your first SMS template to speed up your messaging
            </p>
            <Button>
              <Plus className="mr-2 h-4 w-4" />
              Create Template
            </Button>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
