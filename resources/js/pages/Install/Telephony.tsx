import React from 'react';
import { useTranslation } from 'react-i18next';
import InstallLayout from '@/layouts/InstallLayout';
import TelephonyProviderChooser, { TelephonyProviderChooserProps } from '@/components/telephony/TelephonyProviderChooser';

type Props = TelephonyProviderChooserProps;

/**
 * The install wizard's telephony step. This page supplies only the
 * wizard-specific chrome (the InstallLayout wrapper, its title, and the
 * step number) — the chooser itself lives in TelephonyProviderChooser so
 * it can be reused at a non-wizard route without duplicating this page.
 */
export default function Telephony(props: Props) {
  const { t } = useTranslation();

  return (
    <InstallLayout title={t('install.telephony_provider', 'Telephony Provider')} step={3}>
      <TelephonyProviderChooser {...props} />
    </InstallLayout>
  );
}
