import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { router, Head, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import PermissionPicker from '@/components/permission-picker';
import type { Permission } from '@/types';

interface Customer {
  id: number;
  name: string;
  email: string;
}

interface Props {
  permissions: Permission[];
  customers?: Customer[];
}

export default function CreateAgent({ permissions, customers }: Props) {
  const { t } = useTranslation();
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    permissions: [] as number[],
    parent_user_id: '' as string | number,
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/agents');
  };

  return (
    <AppLayout
      breadcrumbs={[
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Agents', href: '/agents' },
        { title: 'Create', href: '/agents/create' }
      ]}
    >
      <Head title="Create Agent" />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center gap-4">
          <Button
            variant="outline"
            size="icon"
            onClick={() => router.visit('/agents')}
          >
            <ArrowLeft className="h-4 w-4" />
          </Button>
          <div>
            <h1 className="text-3xl font-bold tracking-tight">{t('agents.create')}</h1>
            <p className="text-muted-foreground">
              Add a new agent with custom permissions
            </p>
          </div>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Basic Information */}
          <Card>
            <CardHeader>
              <CardTitle>Agent Information</CardTitle>
              <CardDescription>
                Enter the basic details for the new agent
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="grid gap-6 md:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="name">
                    {t('agents.name')} <span className="text-destructive">*</span>
                  </Label>
                  <Input
                    id="name"
                    type="text"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    placeholder="Enter full name"
                    className={errors.name ? 'border-destructive' : ''}
                  />
                  {errors.name && (
                    <p className="text-sm text-destructive">{errors.name}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="email">
                    {t('common.email')} <span className="text-destructive">*</span>
                  </Label>
                  <Input
                    id="email"
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    placeholder="agent@example.com"
                    className={errors.email ? 'border-destructive' : ''}
                  />
                  {errors.email && (
                    <p className="text-sm text-destructive">{errors.email}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="password">
                    Password <span className="text-destructive">*</span>
                  </Label>
                  <Input
                    id="password"
                    type="password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    placeholder="••••••••"
                    className={errors.password ? 'border-destructive' : ''}
                  />
                  {errors.password && (
                    <p className="text-sm text-destructive">{errors.password}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="password_confirmation">
                    Confirm Password <span className="text-destructive">*</span>
                  </Label>
                  <Input
                    id="password_confirmation"
                    type="password"
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    placeholder="••••••••"
                    className={errors.password_confirmation ? 'border-destructive' : ''}
                  />
                  {errors.password_confirmation && (
                    <p className="text-sm text-destructive">{errors.password_confirmation}</p>
                  )}
                </div>
              </div>

              {/* Customer selector for admin users */}
              {customers && customers.length > 0 && (
                <div className="space-y-2">
                  <Label htmlFor="parent_user_id">
                    {t('agents.assign_to_customer')} <span className="text-destructive">*</span>
                  </Label>
                  <Select
                    value={String(data.parent_user_id)}
                    onValueChange={(value) => setData('parent_user_id', Number(value))}
                  >
                    <SelectTrigger className={errors.parent_user_id ? 'border-destructive' : ''}>
                      <SelectValue placeholder={t('agents.select_customer')} />
                    </SelectTrigger>
                    <SelectContent>
                      {customers.map((customer) => (
                        <SelectItem key={customer.id} value={String(customer.id)}>
                          {customer.name} ({customer.email})
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {errors.parent_user_id && (
                    <p className="text-sm text-destructive">{errors.parent_user_id}</p>
                  )}
                </div>
              )}
            </CardContent>
          </Card>

          {/* Permissions */}
          <Card>
            <CardHeader>
              <CardTitle>Agent Permissions</CardTitle>
              <CardDescription>
                Select which permissions this agent will have. Base agent permissions are included by default.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <PermissionPicker
                permissions={permissions}
                selectedPermissions={data.permissions}
                onChange={(permissionIds) => setData('permissions', permissionIds)}
                disabled={processing}
              />
              {errors.permissions && (
                <p className="text-sm text-destructive mt-2">{errors.permissions}</p>
              )}
            </CardContent>
          </Card>

          {/* Form Actions */}
          <div className="flex justify-end gap-4">
            <Button
              type="button"
              variant="outline"
              onClick={() => router.visit('/agents')}
              disabled={processing}
            >
              {t('common.cancel')}
            </Button>
            <Button type="submit" disabled={processing}>
              {processing ? 'Creating...' : t('agents.create')}
            </Button>
          </div>
        </form>
      </div>
    </AppLayout>
  );
}
