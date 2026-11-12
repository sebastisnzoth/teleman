import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

interface InstallLayoutProps {
  children: React.ReactNode;
  title: string;
  step?: number;
}

export default function InstallLayout({ children, title, step }: InstallLayoutProps) {
  const { t } = useTranslation();
  const appName = (usePage<any>().props.appName ?? usePage<any>().props.name) as string || 'App';
  const steps = [
    { number: 1, name: t('install.step_requirements') },
    { number: 2, name: t('install.step_database') },
    { number: 3, name: t('install.step_telephony', 'Telephony') },
    { number: 4, name: t('install.step_admin_setup') },
  ];

  return (
    <>
      <Head title={title} />
      <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-900 dark:to-gray-800">
        <div className="container mx-auto px-4 py-8">
          {/* Header */}
          <div className="text-center mb-8">
            <h1 className="text-4xl font-bold text-gray-900 dark:text-white mb-2">
              {t('install.installation_title')}
            </h1>
            <p className="text-gray-600 dark:text-gray-400">
              {t('install.follow_steps')}
            </p>
          </div>

          {/* Steps Indicator */}
          {step && (
            <div className="max-w-3xl mx-auto mb-8">
              <div className="flex items-center justify-between">
                {steps.map((s, index) => (
                  <React.Fragment key={s.number}>
                    <div className="flex flex-col items-center">
                      <div
                        className={`w-10 h-10 rounded-full flex items-center justify-center font-semibold ${
                          s.number === step
                            ? 'bg-blue-600 text-white'
                            : s.number < step
                            ? 'bg-green-600 text-white'
                            : 'bg-gray-300 dark:bg-gray-700 text-gray-600 dark:text-gray-400'
                        }`}
                      >
                        {s.number < step ? '✓' : s.number}
                      </div>
                      <span
                        className={`text-sm mt-2 ${
                          s.number === step
                            ? 'text-blue-600 dark:text-blue-400 font-semibold'
                            : 'text-gray-600 dark:text-gray-400'
                        }`}
                      >
                        {s.name}
                      </span>
                    </div>
                    {index < steps.length - 1 && (
                      <div
                        className={`flex-1 h-1 mx-4 ${
                          s.number < step
                            ? 'bg-green-600'
                            : 'bg-gray-300 dark:bg-gray-700'
                        }`}
                      />
                    )}
                  </React.Fragment>
                ))}
              </div>
            </div>
          )}

          {/* Main Content */}
          <div className="max-w-4xl mx-auto">
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-8">
              {children}
            </div>
          </div>

          {/* Footer */}
          <div className="text-center mt-8 text-gray-600 dark:text-gray-400">
            <p className="text-sm">
              © {new Date().getFullYear()} {appName}. All rights reserved.
            </p>
          </div>
        </div>
      </div>
    </>
  );
}
