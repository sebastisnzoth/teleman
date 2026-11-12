import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Link, router, Head } from '@inertiajs/react';
import { 
  ArrowLeft, 
  Edit, 
  Mail, 
  Calendar, 
  Shield, 
  Users, 
  Phone,
  Target,
  UserCheck
} from 'lucide-react';
import type { User } from '@/types';

interface Props {
  user: User;
  stats: {
    campaigns_count: number;
    contacts_count: number;
    calls_count: number;
    agents_count: number;
  };
}

export default function ShowUser({ user, stats }: Props) {
  const { t } = useTranslation();
  const getStatusBadge = (status?: string) => {
    switch (status) {
      case 'active':
        return <Badge variant="default" className="bg-green-500">Active</Badge>;
      case 'inactive':
        return <Badge variant="secondary">Inactive</Badge>;
      case 'suspended':
        return <Badge variant="destructive">Suspended</Badge>;
      default:
        return <Badge variant="outline">Unknown</Badge>;
    }
  };

  const getRoleBadge = () => {
    const role = user.roles?.[0];
    if (!role) return <Badge variant="outline">No Role</Badge>;

    const colors: Record<string, string> = {
      admin: 'bg-purple-500',
      customer: 'bg-blue-500',
      agent: 'bg-gray-500',
    };

    return <Badge className={colors[role.slug] || 'bg-gray-500'}>{role.name}</Badge>;
  };

  return (
    <AppLayout
      breadcrumbs={[
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Users', href: '/users' },
        { title: user.name, href: `/users/${user.id}` }
      ]}
    >
      <Head title={`User - ${user.name}`} />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <Button
              variant="outline"
              size="icon"
              onClick={() => router.visit('/users')}
            >
              <ArrowLeft className="h-4 w-4" />
            </Button>
            <div className="flex items-center gap-4">
              <div className="h-16 w-16 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white text-2xl font-semibold">
                {user.name.charAt(0).toUpperCase()}
              </div>
              <div>
                <h1 className="text-3xl font-bold tracking-tight">{user.name}</h1>
                <p className="text-muted-foreground">{user.email}</p>
              </div>
            </div>
          </div>
          <Link href={`/users/${user.id}/edit`}>
            <Button>
              <Edit className="mr-2 h-4 w-4" />
              {t('users.edit')}
            </Button>
          </Link>
        </div>

        {/* Stats Grid */}
        <div className="grid gap-4 md:grid-cols-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('users.campaigns')}</CardTitle>
              <Target className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.campaigns_count}</div>
              <p className="text-xs text-muted-foreground">
                {t('users.total_campaigns')}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('users.contacts')}</CardTitle>
              <UserCheck className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.contacts_count}</div>
              <p className="text-xs text-muted-foreground">
                {t('users.total_contacts')}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('users.total_calls')}</CardTitle>
              <Phone className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.calls_count}</div>
              <p className="text-xs text-muted-foreground">
                {t('users.total_calls_made')}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('users.agents')}</CardTitle>
              <Users className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.agents_count}</div>
              <p className="text-xs text-muted-foreground">
                {t('users.agents_under_user')}
              </p>
            </CardContent>
          </Card>
        </div>

        <div className="grid gap-6 md:grid-cols-2">
          {/* User Details */}
          <Card>
            <CardHeader>
              <CardTitle>{t('users.user_details')}</CardTitle>
              <CardDescription>{t('users.user_details_description')}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center gap-3">
                <Mail className="h-5 w-5 text-muted-foreground" />
                <div>
                  <p className="text-sm font-medium">{t('common.email')}</p>
                  <p className="text-sm text-muted-foreground">{user.email}</p>
                </div>
              </div>

              <div className="flex items-center gap-3">
                <Shield className="h-5 w-5 text-muted-foreground" />
                <div>
                  <p className="text-sm font-medium">{t('users.role')}</p>
                  <div className="mt-1">{getRoleBadge()}</div>
                </div>
              </div>

              <div className="flex items-center gap-3">
                <UserCheck className="h-5 w-5 text-muted-foreground" />
                <div>
                  <p className="text-sm font-medium">{t('common.status')}</p>
                  <div className="mt-1">{getStatusBadge(user.status)}</div>
                </div>
              </div>

              {user.parent && (
                <div className="flex items-center gap-3">
                  <Users className="h-5 w-5 text-muted-foreground" />
                  <div>
                    <p className="text-sm font-medium">{t('users.parent_user')}</p>
                    <Link
                      href={`/users/${user.parent.id}`}
                      className="text-sm text-primary hover:underline"
                    >
                      {user.parent.name}
                    </Link>
                  </div>
                </div>
              )}

              <div className="flex items-center gap-3">
                <Calendar className="h-5 w-5 text-muted-foreground" />
                <div>
                  <p className="text-sm font-medium">{t('users.last_login')}</p>
                  <p className="text-sm text-muted-foreground">
                    {user.last_login_at
                      ? new Date(user.last_login_at).toLocaleString()
                      : t('users.never')
                    }
                  </p>
                </div>
              </div>

              <div className="flex items-center gap-3">
                <Calendar className="h-5 w-5 text-muted-foreground" />
                <div>
                  <p className="text-sm font-medium">{t('users.member_since')}</p>
                  <p className="text-sm text-muted-foreground">
                    {new Date(user.created_at).toLocaleDateString('en-US', {
                      year: 'numeric',
                      month: 'long',
                      day: 'numeric'
                    })}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Permissions */}
          <Card>
            <CardHeader>
              <CardTitle>{t('users.permissions')}</CardTitle>
              <CardDescription>{t('users.permissions_description')}</CardDescription>
            </CardHeader>
            <CardContent>
              {user.roles?.[0]?.permissions && user.roles[0].permissions.length > 0 ? (
                <div className="space-y-3">
                  {Object.entries(
                    user.roles[0].permissions.reduce((acc, perm) => {
                      if (!acc[perm.module]) acc[perm.module] = [];
                      acc[perm.module]!.push(perm);
                      return acc;
                    }, {} as Record<string, typeof user.roles[0]['permissions']>)
                  ).map(([module, permissions]) => (
                    <div key={module} className="space-y-2">
                      <h4 className="font-medium text-sm capitalize">{module}</h4>
                      <div className="flex flex-wrap gap-2">
                        {permissions?.map((permission) => (
                          <Badge key={permission.id} variant="secondary" className="text-xs">
                            {permission.name}
                          </Badge>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-muted-foreground">{t('users.no_permissions')}</p>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Agents List (if user has agents) */}
        {user.agents && user.agents.length > 0 && (
          <Card>
            <CardHeader>
              <CardTitle>{t('users.agents')}</CardTitle>
              <CardDescription>
                {t('users.agents_managed_by', { name: user.name })}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-2">
                {user.agents.map((agent) => (
                  <Link
                    key={agent.id}
                    href={`/users/${agent.id}`}
                    className="flex items-center justify-between p-3 rounded-lg border hover:bg-accent transition-colors"
                  >
                    <div className="flex items-center gap-3">
                      <div className="h-10 w-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center text-white text-sm font-semibold">
                        {agent.name.charAt(0).toUpperCase()}
                      </div>
                      <div>
                        <p className="font-medium">{agent.name}</p>
                        <p className="text-sm text-muted-foreground">{agent.email}</p>
                      </div>
                    </div>
                    <Badge variant="secondary">
                      {agent.roles?.[0]?.name || 'No Role'}
                    </Badge>
                  </Link>
                ))}
              </div>
            </CardContent>
          </Card>
        )}
      </div>
    </AppLayout>
  );
}
