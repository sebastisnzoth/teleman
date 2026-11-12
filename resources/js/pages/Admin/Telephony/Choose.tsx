import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent } from '@/components/ui/card';
import TelephonyProviderChooser from '@/components/telephony/TelephonyProviderChooser';
import { type BreadcrumbItem } from '@/types';

interface ProviderOption {
  value: string;
  label: string;
}

interface Props {
  provider: string;
  providers: ProviderOption[];
  /** Where the choice is posted. Sent by the controller that owns the route. */
  submitUrl: string;
}

/**
 * Where an admin lands after resetting telephony: pick the provider this
 * installation runs on from now on.
 *
 * Renders the same chooser the install wizard's provider step uses, with
 * copy that fits this screen instead of that one — the installer warns
 * that the choice "cannot be changed from this screen", which is exactly
 * the change being made here. The component takes its heading, warning
 * and submit target as props for that reason.
 *
 * This is an admin route, not the installer route: an installed
 * application must never re-enter the install wizard.
 */
export default function ChooseTelephonyProvider({ provider, providers, submitUrl }: Props) {
  const { t } = useTranslation();

  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: '/dashboard' },
    { title: t('admin.telephony.telephony'), href: '/admin/telephony' },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('admin.telephony.choose_heading')} />

      <div className="max-w-2xl mx-auto">
        <Card>
          <CardContent className="pt-6">
            <TelephonyProviderChooser
              provider={provider}
              providers={providers}
              submitUrl={submitUrl}
              heading={t('admin.telephony.choose_heading')}
              description={t('admin.telephony.choose_description')}
              warning={t('admin.telephony.choose_warning')}
              submitLabel={t('admin.telephony.choose_submit')}
            />
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
