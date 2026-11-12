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
  Phone,
  Target,
  UserCheck,
  Clock
} from 'lucide-react';
import type { User } from '@/types';

interface Props {
  agent: User;
  stats: {
    campaigns_executed: number;
    calls_made: number;
    total_call_duration: number;
    contacts_reached: number;
  };
  recentCalls: Array<{
    id: number;
    status: string;
    duration: number;
    created_at: string;
    campaign?: {
      id: number;
      name: string;
    };
    contact?: {
      id: number;
      phone_number: string;
      first_name: string | null;
      last_name: string | null;
    };
  }>;
}

export default function ShowAgent({ agent, stats, recentCalls }: Props) {
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

  const getCallStatusBadge = (status: string) => {
    switch (status) {
      case 'completed':
      case 'answered':
        return <Badge variant="default" className="bg-green-500">Completed</Badge>;
      case 'no-answer':
        return <Badge variant="secondary">No Answer</Badge>;
      case 'busy':
        return <Badge variant="outline">Busy</Badge>;
      case 'failed':
        return <Badge variant="destructive">Failed</Badge>;
      default:
        return <Badge variant="outline">{status}</Badge>;
    }
  };

  const formatDuration = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  };

  return (
    <AppLayout
      breadcrumbs={[
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Agents', href: '/agents' },
        { title: agent.name, href: `/agents/${agent.id}` }
      ]}
    >
      <Head title={`Agent - ${agent.name}`} />

      <div className="space-y-6">
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
            <div className="flex items-center gap-4">
              <div className="h-16 w-16 rounded-full bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center text-white text-2xl font-semibold">
                {agent.name.charAt(0).toUpperCase()}
              </div>
              <div>
                <h1 className="text-3xl font-bold tracking-tight">{agent.name}</h1>
                <p className="text-muted-foreground">{agent.email}</p>
              </div>
            </div>
          </div>
          <Link href={`/agents/${agent.id}/edit`}>
            <Button>
              <Edit className="mr-2 h-4 w-4" />
              {t('agents.edit')}
            </Button>
          </Link>
        </div>

        {/* Stats Grid */}
        <div className="grid gap-4 md:grid-cols-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Campaigns</CardTitle>
              <Target className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.campaigns_executed}</div>
              <p className="text-xs text-muted-foreground">
                Campaigns executed
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Calls Made</CardTitle>
              <Phone className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.calls_made}</div>
              <p className="text-xs text-muted-foreground">
                Total calls placed
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Call Duration</CardTitle>
              <Clock className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{formatDuration(stats.total_call_duration)}</div>
              <p className="text-xs text-muted-foreground">
                Total talk time
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Contacts Reached</CardTitle>
              <UserCheck className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.contacts_reached}</div>
              <p className="text-xs text-muted-foreground">
                Successfully reached
              </p>
            </CardContent>
          </Card>
        </div>

        <div className="grid gap-6 md:grid-cols-2">
          {/* Agent Details */}
          <Card>
            <CardHeader>
              <CardTitle>{t('agents.agent_details')}</CardTitle>
              <CardDescription>Basic information about this agent</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center gap-3">
                <Mail className="h-5 w-5 text-muted-foreground" />
                <div>
                  <p className="text-sm font-medium">{t('common.email')}</p>
                  <p className="text-sm text-muted-foreground">{agent.email}</p>
                </div>
              </div>

              <div className="flex items-center gap-3">
                <UserCheck className="h-5 w-5 text-muted-foreground" />
                <div>
                  <p className="text-sm font-medium">{t('common.status')}</p>
                  <div className="mt-1">{getStatusBadge(agent.status)}</div>
                </div>
              </div>

              {agent.parent && (
                <div className="flex items-center gap-3">
                  <Shield className="h-5 w-5 text-muted-foreground" />
                  <div>
                    <p className="text-sm font-medium">Managed By</p>
                    <Link 
                      href={`/users/${agent.parent.id}`}
                      className="text-sm text-primary hover:underline"
                    >
                      {agent.parent.name}
                    </Link>
                  </div>
                </div>
              )}

              <div className="flex items-center gap-3">
                <Calendar className="h-5 w-5 text-muted-foreground" />
                <div>
                  <p className="text-sm font-medium">Last Login</p>
                  <p className="text-sm text-muted-foreground">
                    {agent.last_login_at 
                      ? new Date(agent.last_login_at).toLocaleString()
                      : 'Never'
                    }
                  </p>
                </div>
              </div>

              <div className="flex items-center gap-3">
                <Calendar className="h-5 w-5 text-muted-foreground" />
                <div>
                  <p className="text-sm font-medium">Created</p>
                  <p className="text-sm text-muted-foreground">
                    {new Date(agent.created_at).toLocaleDateString('en-US', {
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
              <CardTitle>Permissions</CardTitle>
              <CardDescription>Assigned permissions for this agent</CardDescription>
            </CardHeader>
            <CardContent>
              {agent.roles?.[0]?.permissions && agent.roles[0].permissions.length > 0 ? (
                <div className="space-y-3">
                  {Object.entries(
                    agent.roles[0].permissions.reduce((acc, perm) => {
                      if (!acc[perm.module]) acc[perm.module] = [];
                      acc[perm.module]!.push(perm);
                      return acc;
                    }, {} as Record<string, typeof agent.roles[0]['permissions']>)
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
                <p className="text-sm text-muted-foreground">No permissions assigned</p>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Recent Calls */}
        {recentCalls && recentCalls.length > 0 && (
          <Card>
            <CardHeader>
              <CardTitle>Recent Activity</CardTitle>
              <CardDescription>
                Last {recentCalls.length} calls made by {agent.name}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-2">
                {recentCalls.map((call) => (
                  <Link
                    key={call.id}
                    href={`/calls/${call.id}`}
                    className="flex items-center justify-between p-3 rounded-lg border hover:bg-accent transition-colors"
                  >
                    <div className="flex items-center gap-3">
                      <Phone className="h-4 w-4 text-muted-foreground" />
                      <div>
                        <p className="font-medium">
                          {call.contact?.first_name && call.contact?.last_name
                            ? `${call.contact.first_name} ${call.contact.last_name}`
                            : call.contact?.phone_number || 'Unknown'}
                        </p>
                        <p className="text-sm text-muted-foreground">
                          {call.campaign?.name || 'No Campaign'} • {formatDuration(call.duration)}
                        </p>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      {getCallStatusBadge(call.status)}
                      <span className="text-xs text-muted-foreground">
                        {new Date(call.created_at).toLocaleDateString()}
                      </span>
                    </div>
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
