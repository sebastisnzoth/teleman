import React from 'react';
import { useTranslation } from 'react-i18next';
import { router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle, Phone } from 'lucide-react';

interface ProviderOption {
  value: string;
  label: string;
}

export interface TelephonyProviderChooserProps {
  /** The pre-selected provider — Twilio in the install wizard, so a buyer who clicks straight through gets it. */
  provider: string;
  providers: ProviderOption[];
  /**
   * Where the choice is submitted. This component is also rendered at an
   * admin route after a destructive provider reset, on an application
   * that must never re-enter the install wizard — so this (and `backUrl`
   * below) is a prop rather than a hardcoded install route.
   */
  submitUrl: string;
  /** Where "Back" returns to. Omitted where there is nowhere to go back to. */
  backUrl?: string;
  /** Submit button label once a choice is ready to send. Defaults to the install wizard's own wording ("Next: Create Admin" — this step always leads there in the wizard); a caller outside the wizard should pass its own. */
  submitLabel?: string;
  /** Submit button label while the request is in flight. */
  savingLabel?: string;
  /** Heading above the chooser. Defaults to the install wizard's wording. */
  heading?: string;
  /** One line under the heading. Defaults to the install wizard's wording. */
  description?: string;
  /**
   * The permanence warning. Defaults to the install wizard's wording,
   * which says the choice "cannot be changed from this screen" and is
   * "permanent for this installation" — true in the wizard, and absurd on
   * the screen an admin reaches immediately after resetting telephony,
   * whose whole purpose is making that change. Any caller outside the
   * wizard must pass copy that fits where it is being shown.
   */
  warning?: string;
}

/**
 * The Twilio/Plivo chooser itself — no layout, page title or step number
 * of its own, so it can be dropped into any surrounding chrome (the
 * install wizard today; a destructive admin reset later) without editing
 * it. See `pages/Install/Telephony.tsx` for the install-wizard wrapper
 * that supplies the wizard-specific chrome around this.
 */
export default function TelephonyProviderChooser({
  provider,
  providers,
  submitUrl,
  backUrl,
  submitLabel,
  savingLabel,
  heading,
  description,
  warning,
}: TelephonyProviderChooserProps) {
  const { t } = useTranslation();
  const { data, setData, post, processing, errors } = useForm({ provider });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post(submitUrl);
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center space-x-3">
        <Phone className="w-8 h-8 text-blue-600" />
        <div>
          <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
            {heading ?? t('install.telephony_provider', 'Telephony Provider')}
          </h2>
          <p className="text-gray-600 dark:text-gray-400">
            {description ?? t('install.choose_provider_description', 'Choose which service will power your calls and SMS.')}
          </p>
        </div>
      </div>

      <Alert variant="destructive">
        <AlertCircle className="h-4 w-4" />
        <AlertDescription>
          {warning ??
            t(
              'install.telephony_lock_warning',
              'This cannot be changed from this screen — the choice is permanent for this installation. Switching providers later means resetting telephony, which deletes your provider credentials and trunks and retires your phone numbers in this installation, while keeping your call and SMS history. Nothing is cancelled at the provider itself: your numbers stay live and keep billing until you cancel them in that account yourself.'
            )}
        </AlertDescription>
      </Alert>

      <form onSubmit={handleSubmit} className="space-y-4">
        <RadioGroup
          value={data.provider}
          onValueChange={(value) => setData('provider', value)}
          className="space-y-3"
        >
          {providers.map((option) => (
            <label
              key={option.value}
              htmlFor={`provider-${option.value}`}
              className={`flex items-center justify-between rounded-lg border p-4 cursor-pointer transition-colors ${
                data.provider === option.value
                  ? 'border-blue-600 bg-blue-50 dark:border-blue-500 dark:bg-blue-950/30'
                  : 'border-gray-200 dark:border-gray-700'
              }`}
            >
              <span className="font-medium text-gray-900 dark:text-white">{option.label}</span>
              <RadioGroupItem value={option.value} id={`provider-${option.value}`} />
            </label>
          ))}
        </RadioGroup>
        {errors.provider && (
          <p className="text-sm text-red-600 mt-1">{errors.provider}</p>
        )}

        <div className="flex justify-between pt-4 border-t dark:border-gray-700">
          {backUrl ? (
            <Button type="button" variant="outline" onClick={() => router.visit(backUrl)} disabled={processing}>
              {t('install.back')}
            </Button>
          ) : (
            <span />
          )}

          <Button type="submit" disabled={processing} size="lg">
            {processing
              ? savingLabel ?? t('install.action_saving', 'Saving...')
              : submitLabel ?? t('install.action_next_create_admin')}
          </Button>
        </div>
      </form>
    </div>
  );
}
