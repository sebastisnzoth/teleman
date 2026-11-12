import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { router } from '@inertiajs/react';
import InstallLayout from '@/layouts/InstallLayout';
import { Button } from '@/components/ui/button';
import { CheckCircle2, Home, Copy, Check, Clock, Cpu } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useTelephony } from '@/lib/telephony';

interface Props {
  cronCommand: string;
  queueCommand: string;
  queueCronCommand: string;
  appUrl: string;
}

function CommandBox({ command }: { command: string }) {
  const [copied, setCopied] = useState(false);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(command);
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch {
      /* clipboard unavailable (http, old browser): the text is still selectable */
    }
  };

  return (
    <div className="mt-2 flex items-start gap-2 rounded-md bg-gray-900 p-3 text-left font-mono text-xs text-gray-100">
      <code className="flex-1 break-all">{command}</code>
      <button type="button" onClick={copy} className="shrink-0 text-gray-400 hover:text-white" aria-label="Copy">
        {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
      </button>
    </div>
  );
}

export default function Complete({ cronCommand, queueCommand, queueCronCommand }: Props) {
  const { t } = useTranslation();
  // The wizard's telephony step ran before this page, so the installation
  // is already locked to one provider. Naming the other one here is the
  // difference between a first instruction that works and one that sends
  // a buyer looking for a page they will never have.
  const { provider } = useTelephony();
  const handleGoToLogin = () => {
    router.visit('/login');
  };

  return (
    <InstallLayout title={t('install.installation_complete')}>
      <div className="space-y-6 text-center">
        <div className="flex justify-center">
          <div className="w-20 h-20 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
            <CheckCircle2 className="w-12 h-12 text-green-600 dark:text-green-400" />
          </div>
        </div>

        <div>
          <h2 className="text-3xl font-bold text-gray-900 dark:text-white mb-2">
            {t('install.installation_complete')}!
          </h2>
          <p className="text-gray-600 dark:text-gray-400 text-lg">
            {t('install.app_installed_success')}
          </p>
        </div>

        {/* The two things without which nothing asynchronous ever happens */}
        <Alert className="text-left">
          <Clock className="h-4 w-4" />
          <AlertDescription>
            <strong>{t('install.next_background_title', 'Two required background processes')}</strong>
            <p className="mt-1 text-sm">
              {t('install.next_background_hint', 'Campaigns, imports, follow-ups and emails run in the background. Without these two commands nothing asynchronous will happen.')}
            </p>

            <p className="mt-4 flex items-center gap-2 font-medium">
              <Clock className="h-4 w-4" /> {t('install.next_cron_title', '1. Add this cron job (runs every minute)')}
            </p>
            <CommandBox command={cronCommand} />

            <p className="mt-4 flex items-center gap-2 font-medium">
              <Cpu className="h-4 w-4" /> {t('install.next_queue_title', '2. Keep a queue worker running (Supervisor, pm2 or systemd)')}
            </p>
            <CommandBox command={queueCommand} />
            <p className="mt-3 text-sm">
              {t('install.next_queue_shared_hint', 'On shared hosting, where a process cannot stay running, add this second cron job instead — it drains the queue every minute:')}
            </p>
            <CommandBox command={queueCronCommand} />
          </AlertDescription>
        </Alert>

        <Alert className="text-left">
          <CheckCircle2 className="h-4 w-4" />
          <AlertDescription>
            <strong>{t('install.whats_next')}</strong>
            <ul className="list-disc list-inside mt-2 space-y-1">
              <li>{t('install.whats_next_login')}</li>
              <li>{provider === 'plivo' ? t('install.whats_next_plivo') : t('install.whats_next_twilio')}</li>
              <li>{t('install.whats_next_stripe')}</li>
              <li>{t('install.whats_next_customize')}</li>
              <li>{t('install.whats_next_campaigns')}</li>
            </ul>
          </AlertDescription>
        </Alert>

        <div className="bg-gray-50 dark:bg-gray-900 rounded-lg p-6">
          <h3 className="font-semibold text-gray-900 dark:text-white mb-3">
            {t('install.quick_links')}
          </h3>
          <div className="space-y-2 text-sm text-gray-600 dark:text-gray-400">
            <div className="flex justify-between py-2 border-b dark:border-gray-700">
              <span>Admin Panel:</span>
              <span className="font-mono">/dashboard</span>
            </div>
            <div className="flex justify-between py-2 border-b dark:border-gray-700">
              <span>System Configuration:</span>
              <span className="font-mono">/admin/system-config</span>
            </div>
            <div className="flex justify-between py-2">
              <span>Documentation:</span>
              <span className="font-mono">/documentation</span>
            </div>
          </div>
        </div>

        <div className="pt-6">
          <Button onClick={handleGoToLogin} size="lg" className="px-8">
            <Home className="mr-2 h-5 w-5" />
            {t('install.action_go_to_login')}
          </Button>
        </div>

        <p className="text-sm text-gray-500 dark:text-gray-500">
          {t('install.thank_you')}
        </p>
      </div>
    </InstallLayout>
  );
}
