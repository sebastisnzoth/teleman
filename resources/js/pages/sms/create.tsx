import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ArrowLeft, Phone, Info } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

export default function SmsPhoneNumbers() {
  const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
  {
    title: t('sms.home'), href: dashboard().url,
  },
  {
    title: t('sms.title'), href: '/sms',
  },
  {
    title: 'Phone Numbers',
  },
];
  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('sms.sms_phone_numbers')} />

      <div className="container mx-auto p-6 space-y-6">
        <div className="flex items-center gap-4">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => router.visit('/sms')}
          >
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back to SMS
          </Button>
          <div>
            <h1 className="text-3xl font-bold tracking-tight text-foreground">SMS Phone Numbers</h1>
            <p className="text-muted-foreground mt-1">
              Use your assigned phone numbers for SMS
            </p>
          </div>
        </div>

        <Card className="border-border bg-card max-w-3xl">
          <CardHeader>
            <CardTitle className="text-foreground flex items-center gap-2">
              <Phone className="h-5 w-5" />
              Phone Number Assignment
            </CardTitle>
            <CardDescription>
              How to get SMS phone numbers for your account
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            <Alert className="border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950">
              <Info className="h-4 w-4 text-blue-600 dark:text-blue-400" />
              <AlertTitle className="text-blue-900 dark:text-blue-100">Using Assigned Phone Numbers</AlertTitle>
              <AlertDescription className="text-blue-800 dark:text-blue-200">
                SMS functionality uses your assigned phone numbers from the main phone number management system. 
                You don't need to add phone numbers separately for SMS.
              </AlertDescription>
            </Alert>

            <div className="space-y-4">
              <h3 className="text-lg font-semibold text-foreground">How It Works</h3>
              
              <div className="space-y-3">
                <div className="flex gap-3">
                  <div className="flex-shrink-0 w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-semibold">
                    1
                  </div>
                  <div>
                    <p className="font-medium text-foreground">Get Phone Numbers Assigned</p>
                    <p className="text-sm text-muted-foreground">
                      Contact your administrator to have phone numbers with SMS capability assigned to your account.
                    </p>
                  </div>
                </div>

                <div className="flex gap-3">
                  <div className="flex-shrink-0 w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-semibold">
                    2
                  </div>
                  <div>
                    <p className="font-medium text-foreground">Check SMS Capability</p>
                    <p className="text-sm text-muted-foreground">
                      Your assigned phone numbers that support SMS will automatically appear in the SMS management section.
                    </p>
                  </div>
                </div>

                <div className="flex gap-3">
                  <div className="flex-shrink-0 w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-semibold">
                    3
                  </div>
                  <div>
                    <p className="font-medium text-foreground">Start Using SMS</p>
                    <p className="text-sm text-muted-foreground">
                      Once assigned, you can immediately start sending and receiving SMS messages using these numbers.
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <div className="border-t pt-6">
              <h3 className="text-lg font-semibold text-foreground mb-3">Features</h3>
              <ul className="space-y-2 text-sm text-muted-foreground">
                <li className="flex items-center gap-2">
                  <div className="w-1.5 h-1.5 rounded-full bg-primary"></div>
                  Use the same Twilio configuration as voice calls
                </li>
                <li className="flex items-center gap-2">
                  <div className="w-1.5 h-1.5 rounded-full bg-primary"></div>
                  Assign AI agents to phone numbers for automated responses
                </li>
                <li className="flex items-center gap-2">
                  <div className="w-1.5 h-1.5 rounded-full bg-primary"></div>
                  Support for both SMS and MMS messages
                </li>
                <li className="flex items-center gap-2">
                  <div className="w-1.5 h-1.5 rounded-full bg-primary"></div>
                  Track conversations and message history
                </li>
                <li className="flex items-center gap-2">
                  <div className="w-1.5 h-1.5 rounded-full bg-primary"></div>
                  Unified billing for calls and SMS
                </li>
              </ul>
            </div>

            <div className="flex gap-3 pt-4">
              <Button onClick={() => router.visit('/sms')}>
                Go to SMS Management
              </Button>
              <Button variant="outline" onClick={() => router.visit('/phone-numbers')}>
                View Phone Numbers
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
