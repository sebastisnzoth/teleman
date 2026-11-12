import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head } from '@inertiajs/react';
import { Shield, Users, CheckCircle2 } from 'lucide-react';
import type { Role, BreadcrumbItem } from '@/types';
import Heading from '@/components/heading';
import { PageHelp } from '@/components/page-help';
import { dashboard } from '@/routes';

interface Props {
  roles: Array<Role & {
    permissions_count: number;
    users_count: number;
  }>;
}

const roleColors = {
  'Admin': 'bg-red-100 text-red-800 border-red-200',
  'Customer': 'bg-blue-100 text-blue-800 border-blue-200',
  'Agent': 'bg-green-100 text-green-800 border-green-200',
} as const;

const roleDescriptions = {
  'Admin': 'Full system access with complete control over all features, users, and settings.',
  'Customer': 'Standard user with access to their own campaigns, contacts, and call management.',
  'Agent': 'Limited user working under a Customer with delegated permissions for specific tasks.',
} as const;

export default function RolesIndex({ roles }: Props) {
  const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Roles & Permissions', href: '/roles' },
  ];

  const helpSections = [
    {
      title: 'Role System Overview',
      content: 'The system uses a 3-tier role hierarchy (Admin, Customer, Agent) with granular permission control across 8 modules: Campaigns, Contacts, Calls, Analytics, Scripts, Audio Files, Users, and Settings.',
    },
    {
      title: 'Admin Role',
      content: 'Full system access with complete control over all features, users, and settings. Admins can manage all resources and configure the entire system.',
    },
    {
      title: 'Customer Role',
      content: 'Standard user with access to their own campaigns, contacts, and call management. Customers can create campaigns and manage their own data.',
    },
    {
      title: 'Agent Role',
      content: 'Limited user working under a Customer with delegated permissions for specific tasks. Agents have restricted access based on assigned permissions.',
    },
    {
      title: 'Permissions',
      content: 'Each role has a specific set of permissions that control access to features. Permissions are organized by module (e.g., campaign.create, contact.view).',
    },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Roles & Permissions" />

      <div className="mx-auto max-w-7xl space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <Heading
            title={t('roles.title')}
            description={t('roles.description')}
          />
          <PageHelp title="Roles & Permissions Help" sections={helpSections} />
        </div>

        {/* Info Card */}
        <Card className="border-blue-200 bg-blue-50/50">
          <CardHeader>
            <CardTitle className="text-lg flex items-center gap-2">
              <Shield className="h-5 w-5 text-blue-600" />
              About the Role System
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-muted-foreground">
              This system uses a 3-tier role hierarchy with granular permission control. 
              Roles define what users can do in the system, while permissions provide fine-grained 
              access control across 8 different modules: Campaigns, Contacts, Calls, Analytics, 
              Scripts, Audio Files, Users, and Settings.
            </p>
          </CardContent>
        </Card>

        {/* Roles Grid */}
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {roles.map((role) => (
            <Card key={role.id} className="hover:shadow-lg transition-shadow">
              <CardHeader>
                <div className="flex items-start justify-between">
                  <div className="space-y-1">
                    <CardTitle className="text-xl">{role.name}</CardTitle>
                    <Badge 
                      variant="outline" 
                      className={roleColors[role.name as keyof typeof roleColors] || 'bg-gray-100 text-gray-800'}
                    >
                      Level {role.level}
                    </Badge>
                  </div>
                  <Shield className="h-8 w-8 text-muted-foreground" />
                </div>
                <CardDescription className="mt-3">
                  {roleDescriptions[role.name as keyof typeof roleDescriptions] || role.description}
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  {/* Stats */}
                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-1">
                      <p className="text-sm text-muted-foreground flex items-center gap-1">
                        <CheckCircle2 className="h-4 w-4" />
                        {t('roles.permissions')}
                      </p>
                      <p className="text-2xl font-bold">{role.permissions_count}</p>
                    </div>
                    <div className="space-y-1">
                      <p className="text-sm text-muted-foreground flex items-center gap-1">
                        <Users className="h-4 w-4" />
                        {t('roles.users_count')}
                      </p>
                      <p className="text-2xl font-bold">{role.users_count}</p>
                    </div>
                  </div>

                  {/* Permission Modules */}
                  {role.permissions && role.permissions.length > 0 && (
                    <div className="pt-4 border-t">
                      <p className="text-sm font-medium mb-2">Permission Modules:</p>
                      <div className="flex flex-wrap gap-1.5">
                        {Array.from(new Set(role.permissions.map(p => p.module))).map((module) => (
                          <Badge 
                            key={module} 
                            variant="secondary" 
                            className="text-xs"
                          >
                            {module}
                          </Badge>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Key Permissions */}
                  {role.permissions && role.permissions.length > 0 && (
                    <div className="pt-3 border-t">
                      <p className="text-sm font-medium mb-2">Sample Permissions:</p>
                      <ul className="text-sm text-muted-foreground space-y-1">
                        {role.permissions.slice(0, 4).map((permission) => (
                          <li key={permission.id} className="flex items-center gap-2">
                            <div className="h-1.5 w-1.5 rounded-full bg-primary" />
                            {permission.name}
                          </li>
                        ))}
                        {role.permissions.length > 4 && (
                          <li className="text-xs italic text-muted-foreground/70">
                            + {role.permissions.length - 4} more permissions
                          </li>
                        )}
                      </ul>
                    </div>
                  )}
                </div>
              </CardContent>
            </Card>
          ))}
        </div>

        {/* Permission Matrix Info */}
        <Card>
          <CardHeader>
            <CardTitle>Permission Structure</CardTitle>
            <CardDescription>
              Permissions are organized into modules with specific actions
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
              <div>
                <h4 className="font-semibold text-sm mb-2">Campaigns</h4>
                <ul className="text-sm text-muted-foreground space-y-1">
                  <li>• View campaigns</li>
                  <li>• Create campaigns</li>
                  <li>• Edit campaigns</li>
                  <li>• Delete campaigns</li>
                  <li>• Launch/Control</li>
                </ul>
              </div>
              <div>
                <h4 className="font-semibold text-sm mb-2">Contacts</h4>
                <ul className="text-sm text-muted-foreground space-y-1">
                  <li>• View contacts</li>
                  <li>• Add contacts</li>
                  <li>• Edit contacts</li>
                  <li>• Delete contacts</li>
                  <li>• Import/Export</li>
                </ul>
              </div>
              <div>
                <h4 className="font-semibold text-sm mb-2">Calls</h4>
                <ul className="text-sm text-muted-foreground space-y-1">
                  <li>• View call logs</li>
                  <li>• Initiate calls</li>
                  <li>• End calls</li>
                  <li>• View recordings</li>
                  <li>• Download recordings</li>
                </ul>
              </div>
              <div>
                <h4 className="font-semibold text-sm mb-2">Analytics</h4>
                <ul className="text-sm text-muted-foreground space-y-1">
                  <li>• View reports</li>
                  <li>• Export reports</li>
                  <li>• View metrics</li>
                  <li>• View dashboards</li>
                  <li>• Compare data</li>
                </ul>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Hierarchy Info */}
        <Card className="border-amber-200 bg-amber-50/50">
          <CardHeader>
            <CardTitle className="text-lg">Role Hierarchy</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              <div className="flex items-center gap-3">
                <Badge className="bg-red-600 hover:bg-red-700">Level 1</Badge>
                <span className="font-semibold">Admin</span>
                <span className="text-muted-foreground text-sm">→ Complete system access</span>
              </div>
              <div className="flex items-center gap-3 ml-6">
                <Badge className="bg-blue-600 hover:bg-blue-700">Level 2</Badge>
                <span className="font-semibold">Customer</span>
                <span className="text-muted-foreground text-sm">→ Owns data and can manage agents</span>
              </div>
              <div className="flex items-center gap-3 ml-12">
                <Badge className="bg-green-600 hover:bg-green-700">Level 3</Badge>
                <span className="font-semibold">Agent</span>
                <span className="text-muted-foreground text-sm">→ Works under Customer with delegated permissions</span>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
