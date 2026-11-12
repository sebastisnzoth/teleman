import React, { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { router, useForm } from '@inertiajs/react';
import axios from 'axios';
import InstallLayout from '@/layouts/InstallLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { CheckCircle2, AlertCircle, Loader2, Database } from 'lucide-react';

interface Props {
  env: {
    DB_HOST: string;
    DB_PORT: string;
    DB_DATABASE: string;
    DB_USERNAME: string;
  };
}

/** One slice of progress as reported by POST /install/database/step. */
interface StepResult {
  ok: boolean;
  stage: 'migrate' | 'seed' | 'finalize' | 'done' | 'blocked';
  completed: number;
  total: number;
  current: string | null;
  done: boolean;
  message?: string;
  reason?: string;
}

type Phase = 'idle' | 'configuring' | 'running' | 'error' | 'not_empty' | 'done';

/** Error payloads the installer endpoints return (422 / 409 / 500). */
interface ApiFailure {
  ok?: boolean;
  message?: string;
  reason?: string;
  table_count?: number;
  errors?: Record<string, string[]>;
}

interface HttpError {
  message?: string;
  response?: { data?: ApiFailure };
}

/** Inertia visit payloads we read back from the connection test. */
interface FlashPage {
  props?: { flash?: { message?: string } };
}

export default function DatabaseConfig({ env }: Props) {
  const { t } = useTranslation();
  const [testing, setTesting] = useState(false);
  const [testResult, setTestResult] = useState<{ success: boolean; message: string } | null>(null);

  const [phase, setPhase] = useState<Phase>('idle');
  const [progress, setProgress] = useState<StepResult | null>(null);
  const [errorMessage, setErrorMessage] = useState<string>('');
  const [tableCount, setTableCount] = useState<number>(0);
  const [wipeConfirmed, setWipeConfirmed] = useState(false);
  const cancelled = useRef(false);

  const form = useForm({
    host: env.DB_HOST || '127.0.0.1',
    port: env.DB_PORT || '3306',
    database: env.DB_DATABASE || '',
    username: env.DB_USERNAME || '',
    password: '',
  });

  const testForm = useForm({
    host: env.DB_HOST || '127.0.0.1',
    port: env.DB_PORT || '3306',
    database: env.DB_DATABASE || '',
    username: env.DB_USERNAME || '',
    password: '',
  });

  // Reset test result when form values change so user must re-test
  const isInitialMount = useRef(true);
  useEffect(() => {
    if (isInitialMount.current) {
      isInitialMount.current = false;
      return;
    }
    if (testResult) {
      setTestResult(null);
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form.data.host, form.data.port, form.data.database, form.data.username, form.data.password]);

  useEffect(() => () => { cancelled.current = true; }, []);

  const handleTestConnection = () => {
    setTesting(true);
    setTestResult(null);

    // Sync test form data with main form
    testForm.setData(form.data);

    testForm.post('/install/database/test', {
      preserveScroll: true,
      onSuccess: (page) => {
        const flash = (page as unknown as FlashPage).props?.flash;
        setTestResult({
          success: true,
          message: flash?.message || t('install.database_success'),
        });
        setTesting(false);
      },
      onError: (errors) => {
        const first = Object.values(errors)[0];
        setTestResult({
          success: false,
          message: errors.message || (typeof first === 'string' ? first : '') || t('install.connection_test_failed'),
        });
        setTesting(false);
      },
    });
  };

  /**
   * Keep asking the server for the next slice until it reports "done".
   * Every call is independent: a timeout, a dropped connection or a closed
   * tab loses nothing — the next call resumes where the last one stopped.
   */
  const runSteps = async () => {
    setPhase('running');
    setErrorMessage('');

    while (!cancelled.current) {
      try {
        const { data } = await axios.post<StepResult>('/install/database/step');
        setProgress(data);

        if (data.done) {
          setPhase('done');
          router.visit('/install/telephony');
          return;
        }
      } catch (error) {
        const { response, message } = error as HttpError;
        const payload = response?.data;
        setErrorMessage(payload?.message || message || t('install.migration_failed'));
        setPhase(payload?.reason === 'not_empty' ? 'not_empty' : 'error');
        return;
      }
    }
  };

  const handleMigrate = async (wipe = false) => {
    setPhase('configuring');
    setErrorMessage('');

    try {
      await axios.post('/install/database/migrate', { ...form.data, driver: 'mysql', wipe });
    } catch (error) {
      const { response, message } = error as HttpError;
      const payload: ApiFailure = response?.data ?? {};

      if (payload.reason === 'not_empty') {
        setTableCount(payload.table_count ?? 0);
        setWipeConfirmed(false);
        setPhase('not_empty');
        return;
      }

      const errors = payload.errors ? Object.values(payload.errors).flat() : [];
      setErrorMessage(payload.message || errors[0] || message || t('install.migration_failed'));
      setPhase('error');
      return;
    }

    await runSteps();
  };

  const canMigrate = testResult?.success === true;
  const busy = phase === 'configuring' || phase === 'running';
  const percent = progress && progress.total > 0 ? Math.round((progress.completed / progress.total) * 100) : 0;

  const stageLabel = (stage?: StepResult['stage']) => {
    switch (stage) {
      case 'migrate':
        return t('install.progress_migrating', 'Creating database tables');
      case 'seed':
        return t('install.progress_seeding', 'Loading default data');
      case 'finalize':
        return t('install.progress_finalizing', 'Finishing up');
      case 'done':
        return t('install.progress_done', 'Database ready');
      default:
        return t('install.migration_running');
    }
  };

  return (
    <InstallLayout title={t('install.database_configuration')} step={2}>
      <div className="space-y-6">
        <div className="flex items-center space-x-3">
          <Database className="w-8 h-8 text-blue-600" />
          <div>
            <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
              {t('install.database_configuration')}
            </h2>
            <p className="text-gray-600 dark:text-gray-400">
              {t('install.configure_db_settings')}
            </p>
          </div>
        </div>

        {testResult && phase === 'idle' && (
          <Alert variant={testResult.success ? 'default' : 'destructive'}>
            {testResult.success ? (
              <CheckCircle2 className="h-4 w-4" />
            ) : (
              <AlertCircle className="h-4 w-4" />
            )}
            <AlertDescription>{testResult.message}</AlertDescription>
          </Alert>
        )}

        <Alert>
          <Database className="h-4 w-4" />
          <AlertDescription>
            {t('install.database_help', 'First create an empty MySQL database and a user with full rights to it in your hosting panel (cPanel: "MySQL Database Wizard"), then enter them here. On most shared hosts the host is "localhost".')}
          </AlertDescription>
        </Alert>

        <div className="space-y-4">
          {/* Database Host */}
          <div>
            <Label htmlFor="host">{t('install.database_host')}</Label>
            <Input
              id="host"
              type="text"
              value={form.data.host}
              onChange={(e) => form.setData('host', e.target.value)}
              placeholder="127.0.0.1"
              className="mt-1"
              disabled={busy}
            />
            {form.errors.host && (
              <p className="text-sm text-red-600 mt-1">{form.errors.host}</p>
            )}
          </div>

          {/* Database Port */}
          <div>
            <Label htmlFor="port">{t('install.database_port')}</Label>
            <Input
              id="port"
              type="text"
              value={form.data.port}
              onChange={(e) => form.setData('port', e.target.value)}
              placeholder="3306"
              className="mt-1"
              disabled={busy}
            />
            {form.errors.port && (
              <p className="text-sm text-red-600 mt-1">{form.errors.port}</p>
            )}
          </div>

          {/* Database Name */}
          <div>
            <Label htmlFor="database">{t('install.database_name')}</Label>
            <Input
              id="database"
              type="text"
              value={form.data.database}
              onChange={(e) => form.setData('database', e.target.value)}
              placeholder="teleman_ai"
              className="mt-1"
              disabled={busy}
            />
            {form.errors.database && (
              <p className="text-sm text-red-600 mt-1">{form.errors.database}</p>
            )}
          </div>

          {/* Database Username */}
          <div>
            <Label htmlFor="username">{t('install.database_username')}</Label>
            <Input
              id="username"
              type="text"
              value={form.data.username}
              onChange={(e) => form.setData('username', e.target.value)}
              placeholder="root"
              className="mt-1"
              disabled={busy}
            />
            {form.errors.username && (
              <p className="text-sm text-red-600 mt-1">{form.errors.username}</p>
            )}
          </div>

          {/* Database Password */}
          <div>
            <Label htmlFor="password">{t('install.database_password')}</Label>
            <Input
              id="password"
              type="password"
              value={form.data.password}
              onChange={(e) => form.setData('password', e.target.value)}
              placeholder={t('install.database_password_placeholder')}
              className="mt-1"
              disabled={busy}
            />
            {form.errors.password && (
              <p className="text-sm text-red-600 mt-1">{form.errors.password}</p>
            )}
          </div>
        </div>

        {/* Existing tables found: nothing is dropped without an explicit yes */}
        {phase === 'not_empty' && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>
              <p className="font-semibold">{t('install.db_not_empty_title', 'This database already contains tables')}</p>
              <p className="mt-1">
                {t('install.db_not_empty_body', { count: tableCount, defaultValue: '{{count}} tables were found. Use an empty database, or erase everything in it and continue.' })}
              </p>
              <label className="mt-3 flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={wipeConfirmed}
                  onChange={(e) => setWipeConfirmed(e.target.checked)}
                />
                {t('install.db_wipe_confirm', 'Erase all existing tables in this database')}
              </label>
              <div className="mt-3">
                <Button
                  variant="destructive"
                  size="sm"
                  disabled={!wipeConfirmed}
                  onClick={() => handleMigrate(true)}
                >
                  {t('install.action_wipe_and_continue', 'Erase and continue')}
                </Button>
              </div>
            </AlertDescription>
          </Alert>
        )}

        {/* Progress */}
        {(phase === 'running' || phase === 'configuring' || phase === 'done') && (
          <div className="rounded-lg border p-4 dark:border-gray-700" role="status" aria-live="polite">
            <div className="flex items-center justify-between text-sm">
              <span className="flex items-center gap-2 font-medium text-gray-900 dark:text-white">
                {phase === 'done' ? (
                  <CheckCircle2 className="h-4 w-4 text-green-600" />
                ) : (
                  <Loader2 className="h-4 w-4 animate-spin" />
                )}
                {phase === 'configuring' ? t('install.action_test_connection') : stageLabel(progress?.stage)}
              </span>
              {progress && progress.total > 0 && (
                <span className="text-gray-500 dark:text-gray-400">
                  {progress.completed} / {progress.total}
                </span>
              )}
            </div>
            <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
              <div
                className="h-2 rounded-full bg-blue-600 transition-all"
                style={{ width: `${phase === 'done' ? 100 : percent}%` }}
              />
            </div>
            {progress?.current && phase !== 'done' && (
              <p className="mt-2 truncate font-mono text-xs text-gray-500 dark:text-gray-400">{progress.current}</p>
            )}
            <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
              {t('install.progress_resume_hint', 'If this page is interrupted, just click the button again — installation continues where it left off.')}
            </p>
          </div>
        )}

        {phase === 'error' && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>
              <p>{errorMessage}</p>
              <div className="mt-3">
                <Button size="sm" variant="outline" onClick={() => runSteps()}>
                  {t('install.action_retry', 'Retry')}
                </Button>
              </div>
            </AlertDescription>
          </Alert>
        )}

        {/* Action Buttons */}
        <div className="flex justify-between pt-4 border-t dark:border-gray-700">
          <Button
            variant="outline"
            onClick={() => router.visit('/install/requirements')}
            disabled={busy}
          >
            {t('install.back')}
          </Button>

          <div className="flex space-x-3">
            <Button
              variant="outline"
              onClick={handleTestConnection}
              disabled={testing || busy}
            >
              {testing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              {t('install.action_test_connection')}
            </Button>

            <Button
              onClick={() => handleMigrate(false)}
              disabled={!canMigrate || busy || phase === 'not_empty'}
            >
              {busy && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              {busy ? t('install.action_migrating_database') : t('install.action_next_choose_provider', 'Next: Choose Provider')}
            </Button>
          </div>
        </div>
      </div>
    </InstallLayout>
  );
}
