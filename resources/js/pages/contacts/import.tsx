import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Upload, ArrowLeft } from 'lucide-react';
import { useEffect } from 'react';
import { BreadcrumbItem } from '@/types';

export default function ContactImport() {
  const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: '/dashboard' },
    { title: t('contacts.title'), href: '/contacts' },
    { title: t('contacts.import_contacts'), href: '/contacts-import' },
  ];

  // Redirect to contacts page where they can use the Import Modal
  useEffect(() => {
    router.get('/contacts');
  }, []);

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('contacts.import_contacts')} />

      <div className="mx-auto max-w-2xl space-y-6">
        <Card className="p-12 text-center">
          <div className="mx-auto w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mb-6">
            <Upload className="h-8 w-8 text-blue-600" />
          </div>
          
          <h1 className="text-2xl font-bold mb-4">Import Contacts</h1>
          <p className="text-muted-foreground mb-8">
            Redirecting you to the contacts page where you can use the Import feature...
          </p>

          <div className="flex justify-center gap-3">
            <Button onClick={() => router.get('/contacts')}>
              <ArrowLeft className="mr-2 h-4 w-4" />
              Go to Contacts
            </Button>
          </div>
        </Card>
      </div>
    </AppLayout>
  );
}
