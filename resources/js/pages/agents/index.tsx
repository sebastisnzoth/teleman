import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Link, router, Head } from '@inertiajs/react';
import { useState } from 'react';
import { 
  Search, 
  Plus, 
  MoreVertical,
  Eye,
  Edit,
  Trash2,
  UserCheck,
  UserX
} from 'lucide-react';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import type { User } from '@/types';
import Heading from '@/components/heading';
import { PageHelp } from '@/components/page-help';
import { dashboard } from '@/routes';

interface PaginatedAgents {
  data: User[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
}

interface Props {
  agents: PaginatedAgents;
  filters: {
    search?: string;
    status?: string;
    sort_field?: string;
    sort_direction?: string;
  };
}

export default function AgentsIndex({ agents, filters }: Props) {
  const { t } = useTranslation();
  const [search, setSearch] = useState(filters.search || '');
  const [selectedStatus, setSelectedStatus] = useState(filters.status || 'all');
  const [deleteAgentId, setDeleteAgentId] = useState<number | null>(null);

  const helpSections = [
    {
      title: 'Agent Management',
      content: 'Agents are team members who can make calls, manage campaigns, and perform tasks based on their assigned roles and permissions.',
    },
    {
      title: 'Adding Agents',
      content: 'Click "Add Agent" to invite new team members. Provide their email, name, and assign appropriate roles to control their access level.',
    },
    {
      title: 'Agent Status',
      content: 'Active agents can log in and perform tasks. Inactive agents are blocked from accessing the system but their data is preserved.',
    },
    {
      title: 'Roles and Permissions',
      content: 'Each agent is assigned a role that determines what features they can access. Configure roles to match your team structure.',
    },
    {
      title: 'Managing Agents',
      content: 'Use the actions menu to view details, edit information, toggle status, or remove agents from your team.',
    },
  ];

  const handleSearch = (value: string) => {
    setSearch(value);
    router.get('/agents', {
      search: value,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
    }, { preserveState: true });
  };

  const handleStatusFilter = (value: string) => {
    setSelectedStatus(value);
    router.get('/agents', {
      search,
      status: value !== 'all' ? value : undefined,
    }, { preserveState: true });
  };

  const handleToggleStatus = (agentId: number) => {
    router.post(`/agents/${agentId}/toggle-status`, {}, {
      preserveScroll: true,
      onSuccess: () => {
        // Success handled by flash message
      }
    });
  };

  const handleDelete = () => {
    if (deleteAgentId) {
      router.delete(`/agents/${deleteAgentId}`, {
        preserveScroll: true,
        onSuccess: () => {
          setDeleteAgentId(null);
        }
      });
    }
  };

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

  return (
    <AppLayout
      breadcrumbs={[
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Agents', href: '/agents' }
      ]}
    >
      <Head title="Agents Management" />

      <div className="mx-auto max-w-7xl space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <Heading title={t('agents.title')} description={t('agents.description')} />
          <div className="flex items-center gap-2">
            <PageHelp title="Agents Help" sections={helpSections} />
            <Link href="/agents/create">
              <Button>
                <Plus className="mr-2 h-4 w-4" />
                {t('agents.add')}
              </Button>
            </Link>
          </div>
        </div>

        {/* Filters Card */}
        <Card>
          <CardHeader>
            <CardTitle>Filters</CardTitle>
            <CardDescription>Search and filter agents</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 md:grid-cols-2">
              <div className="relative">
                <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder={t('agents.search')}
                  value={search}
                  onChange={(e) => handleSearch(e.target.value)}
                  className="pl-9"
                />
              </div>

              <Select value={selectedStatus} onValueChange={handleStatusFilter}>
                <SelectTrigger>
                  <SelectValue placeholder="Filter by status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Statuses</SelectItem>
                  <SelectItem value="active">Active</SelectItem>
                  <SelectItem value="inactive">Inactive</SelectItem>
                  <SelectItem value="suspended">Suspended</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </CardContent>
        </Card>

        {/* Agents Table */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle>All Agents</CardTitle>
                <CardDescription>
                  Showing {agents.from} to {agents.to} of {agents.total} agents
                </CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('agents.name')}</TableHead>
                  <TableHead>{t('common.email')}</TableHead>
                  <TableHead>{t('common.status')}</TableHead>
                  <TableHead>Parent</TableHead>
                  <TableHead>{t('roles.permissions')}</TableHead>
                  <TableHead>Last Login</TableHead>
                  <TableHead className="text-right">{t('common.actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {agents.data.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={7} className="text-center text-muted-foreground">
                      {t('agents.no_agents')}
                    </TableCell>
                  </TableRow>
                ) : (
                  agents.data.map((agent) => (
                    <TableRow key={agent.id}>
                      <TableCell className="font-medium">
                        <div className="flex items-center gap-2">
                          <div className="h-8 w-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center text-white text-sm font-semibold">
                            {agent.name.charAt(0).toUpperCase()}
                          </div>
                          {agent.name}
                        </div>
                      </TableCell>
                      <TableCell>{agent.email}</TableCell>
                      <TableCell>{getStatusBadge(agent.status)}</TableCell>
                      <TableCell>
                        {agent.parent ? (
                          <span className="text-sm text-muted-foreground">
                            {agent.parent.name}
                          </span>
                        ) : (
                          <span className="text-sm text-muted-foreground">-</span>
                        )}
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline">
                          {agent.permissions?.length || 0} permissions
                        </Badge>
                      </TableCell>
                      <TableCell>
                        {agent.last_login_at ? (
                          <span className="text-sm">
                            {new Date(agent.last_login_at).toLocaleDateString()}
                          </span>
                        ) : (
                          <span className="text-sm text-muted-foreground">Never</span>
                        )}
                      </TableCell>
                      <TableCell className="text-right">
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm">
                              <MoreVertical className="h-4 w-4" />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem asChild>
                              <Link href={`/agents/${agent.id}`}>
                                <Eye className="mr-2 h-4 w-4" />
                                {t('common.view')}
                              </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                              <Link href={`/agents/${agent.id}/edit`}>
                                <Edit className="mr-2 h-4 w-4" />
                                {t('common.edit')}
                              </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => handleToggleStatus(agent.id)}>
                              {agent.status === 'active' ? (
                                <>
                                  <UserX className="mr-2 h-4 w-4" />
                                  Deactivate
                                </>
                              ) : (
                                <>
                                  <UserCheck className="mr-2 h-4 w-4" />
                                  Activate
                                </>
                              )}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                              className="text-destructive"
                              onClick={() => setDeleteAgentId(agent.id)}
                            >
                              <Trash2 className="mr-2 h-4 w-4" />
                              {t('common.delete')}
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>

            {/* Pagination */}
            {agents.last_page > 1 && (
              <div className="mt-4 flex items-center justify-between">
                <p className="text-sm text-muted-foreground">
                  Page {agents.current_page} of {agents.last_page}
                </p>
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={agents.current_page === 1}
                    onClick={() => router.get('/agents', {
                      ...filters,
                      page: agents.current_page - 1
                    })}
                  >
                    Previous
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={agents.current_page === agents.last_page}
                    onClick={() => router.get('/agents', {
                      ...filters,
                      page: agents.current_page + 1
                    })}
                  >
                    Next
                  </Button>
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Delete Confirmation Dialog */}
      <Dialog open={deleteAgentId !== null} onOpenChange={() => setDeleteAgentId(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Are you sure?</DialogTitle>
            <DialogDescription>
              This action cannot be undone. This will permanently delete the agent
              and remove their data from the system.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDeleteAgentId(null)}>
              {t('common.cancel')}
            </Button>
            <Button onClick={handleDelete} variant="destructive">
              {t('agents.delete')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </AppLayout>
  );
}
