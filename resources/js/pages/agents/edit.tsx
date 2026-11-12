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
import Heading from '@/components/heading';
import { PageHelp } from '@/components/page-help';
import { dashboard } from '@/routes';
import type { User, Permission, BreadcrumbItem } from '@/types';

interface Props {
  agent: User;
  permissions: Permission[];
  agentPermissions: number[];
}

export default function EditAgent({ agent, permissions, agentPermissions }: Props) {
  const { t } = useTranslation();
  const { data, setData, put, processing, errors } = useForm({
    name: agent.name || '',
    email: agent.email || '',
    password: '',
    password_confirmation: '',
    status: (agent.status || 'active') as 'active' | 'inactive' | 'suspended',
    permissions: agentPermissions || [],
  });

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Agents', href: '/agents' },
    { title: 'Edit Agent', href: `/agents/${agent.id}/edit` },
  ];

  const helpSections = [
    {
      title: 'Edit Agent Overview',
      content: 'Update your agent\'s profile information, credentials, and permissions. Changes take effect immediately after saving.',
    },
    {
      title: 'Agent Information',
      content: 'Update the agent\'s name and email address. The email must be unique and will be used for login and notifications.',
    },
    {
      title: 'Password Management',
      content: 'Leave the password fields blank to keep the current password. If you enter a new password, make sure to confirm it in both fields. Passwords must meet minimum security requirements.',
    },
    {
      title: 'Agent Status',
      content: 'Active: Agent can log in and perform tasks. Inactive: Agent cannot log in but data is preserved. Suspended: Temporarily blocked from accessing the system.',
    },
    {
      title: 'Permission Management',
      content: 'Select which actions this agent can perform. Base agent permissions are included automatically. Additional permissions can be granted based on your requirements.',
    },
    {
      title: 'Saving Changes',
      content: 'Click "Save Changes" to apply all updates. The agent will need to log out and log back in for permission changes to take effect.',
    },
  ];

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(`/agents/${agent.id}`);
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={`Edit Agent - ${agent.name}`} />

      <div className="mx-auto max-w-7xl space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <Button
              variant="outline"
              size="icon"
              onClick={() => router.visit('/agents')}
            >
              <ArrowLeft className="h-4 w-4" />
            </Button>
            <Heading
              title={t('agents.edit')}
              description={`Update ${agent.name}'s information and permissions`}
            />
          </div>
          <PageHelp title="Edit Agent Help" sections={helpSections} />
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Basic Information */}
          <Card>
            <CardHeader>
              <CardTitle>Agent Information</CardTitle>
              <CardDescription>
                Update the agent's basic details
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
                    Password <span className="text-muted-foreground">(leave blank to keep current)</span>
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
                    Confirm Password
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

                <div className="space-y-2">
                  <Label htmlFor="status">
                    {t('common.status')} <span className="text-destructive">*</span>
                  </Label>
                  <Select
                    value={data.status}
                    onValueChange={(value: 'active' | 'inactive' | 'suspended') => setData('status', value)}
                  >
                    <SelectTrigger className={errors.status ? 'border-destructive' : ''}>
                      <SelectValue placeholder="Select status" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="active">Active</SelectItem>
                      <SelectItem value="inactive">Inactive</SelectItem>
                      <SelectItem value="suspended">Suspended</SelectItem>
                    </SelectContent>
                  </Select>
                  {errors.status && (
                    <p className="text-sm text-destructive">{errors.status}</p>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Permissions */}
          <Card>
            <CardHeader>
              <CardTitle>Agent Permissions</CardTitle>
              <CardDescription>
                Customize which permissions this agent has. Base agent permissions are included by default.
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
              {processing ? 'Saving...' : t('common.save')}
            </Button>
          </div>
        </form>
      </div>
    </AppLayout>
  );
}
