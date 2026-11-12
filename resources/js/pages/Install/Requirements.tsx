import React from 'react';
import { useTranslation } from 'react-i18next';
import { router } from '@inertiajs/react';
import InstallLayout from '@/layouts/InstallLayout';
import { Button } from '@/components/ui/button';
import { CheckCircle2, XCircle, AlertCircle } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface RequirementItem {
  name: string;
  passed: boolean;
  current?: string;
  /** Plain-language fix, shown when the check fails. */
  hint?: string;
}

interface RequirementGroup {
  title: string;
  items: RequirementItem[];
}

// Groups are defined server-side; render whatever arrives, in order.
type Requirements = Record<string, RequirementGroup>;

interface Props {
  requirements: Requirements;
}

export default function Requirements({ requirements }: Props) {
  const { t } = useTranslation();
  const allPassed = Object.values(requirements).every((group) =>
    group.items.every((item: RequirementItem) => item.passed)
  );

  const handleNext = () => {
    router.post('/install/requirements/check');
  };

  const renderRequirementItem = (item: RequirementItem) => (
    <div
      key={item.name}
      className="py-3 border-b dark:border-gray-700 last:border-0"
    >
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-3">
          {item.passed ? (
            <CheckCircle2 className="w-5 h-5 text-green-600" />
          ) : (
            <XCircle className="w-5 h-5 text-red-600" />
          )}
          <span className="text-gray-700 dark:text-gray-300">{item.name}</span>
        </div>
        {item.current && (
          <span className="text-sm text-gray-500 dark:text-gray-400">
            {item.current}
          </span>
        )}
      </div>
      {!item.passed && item.hint && (
        <p className="mt-1 pl-8 text-sm text-red-700 dark:text-red-300">{item.hint}</p>
      )}
    </div>
  );

  return (
    <InstallLayout title={t('install.requirements_check')} step={1}>
      <div className="space-y-6">
        <div>
          <h2 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">
            {t('install.system_requirements')}
          </h2>
          <p className="text-gray-600 dark:text-gray-400">
            {t('install.ensure_requirements')}
          </p>
        </div>

        {!allPassed && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>
              {t('install.requirements_not_met')}
            </AlertDescription>
          </Alert>
        )}

        <div className="space-y-6">
          {Object.entries(requirements).map(([key, group]) => (
            <div key={key} className="bg-gray-50 dark:bg-gray-900 rounded-lg p-6">
              <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                {group.title}
              </h3>
              <div className={key === 'extensions' ? 'grid grid-cols-1 md:grid-cols-2 gap-1' : 'space-y-1'}>
                {group.items.map(renderRequirementItem)}
              </div>
            </div>
          ))}
        </div>

        {/* Action Buttons */}
        <div className="flex justify-end pt-4">
          <Button
            onClick={handleNext}
            disabled={!allPassed}
            size="lg"
            className="px-8"
          >
            {t('install.action_next_database_setup')}
          </Button>
        </div>
      </div>
    </InstallLayout>
  );
}
