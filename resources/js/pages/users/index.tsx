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
  UserX,
  UserCog,
  ShieldCheck,
  Shield,
  ShieldX
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
import type { User, Role } from '@/types';
import Heading from '@/components/heading';
import { PageHelp } from '@/components/page-help';
import { dashboard } from '@/routes';

interface PaginatedUsers {
  data: User[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
}

interface Props {
  users: PaginatedUsers;
  roles: Role[];
  filters: {
    search?: string;
    role?: string;
    status?: string;
    sort_field?: string;
    sort_direction?: string;
  };
}

export default function UsersIndex({ users, roles, filters }: Props) {
  const { t } = useTranslation();
  const [search, setSearch] = useState(filters.search || '');
  const [selectedRole, setSelectedRole] = useState(filters.role || 'all');
  const [selectedStatus, setSelectedStatus] = useState(filters.status || 'all');
  const [deleteUserId, setDeleteUserId] = useState<number | null>(null);

  const helpSections = [
    {
      title: 'User Management',
      content: 'Manage all users in your system. Users can be customers using the service or team members with administrative access.',
    },
    {
      title: 'Adding Users',
      content: 'Click "Add User" to create new user accounts. Assign roles to define their access level and permissions.',
    },
    {
      title: 'User Roles',
      content: 'Admin: Full system access. Customer: Standard user access. Agent: Limited access for support staff. Configure roles in the Roles section.',
    },
    {
      title: 'User Status',
      content: 'Active users can log in and use the system. Inactive users are blocked from access but their data is retained.',
    },
    {
      title: 'Filtering and Search',
      content: 'Search by name or email. Filter by role or status to quickly find specific users.',
    },
  ];

  const handleSearch = (value: string) => {
    setSearch(value);
    router.get('/users', {
      search: value,
      role: selectedRole !== 'all' ? selectedRole : undefined,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
    }, { preserveState: true });
  };

  const handleRoleFilter = (value: string) => {
    setSelectedRole(value);
    router.get('/users', {
      search,
      role: value !== 'all' ? value : undefined,
      status: selectedStatus !== 'all' ? selectedStatus : undefined,
    }, { preserveState: true });
  };

  const handleStatusFilter = (value: string) => {
    setSelectedStatus(value);
    router.get('/users', {
      search,
      role: selectedRole !== 'all' ? selectedRole : undefined,
      status: value !== 'all' ? value : undefined,
    }, { preserveState: true });
  };

  const handleToggleStatus = (userId: number) => {
    router.post(`/users/${userId}/toggle-status`, {}, {
      preserveScroll: true,
      onSuccess: () => {
        // Success handled by flash message
      }
    });
  };

  const handleDelete = () => {
    if (deleteUserId) {
      router.delete(`/users/${deleteUserId}`, {
        preserveScroll: true,
        onSuccess: () => {
          setDeleteUserId(null);
        }
      });
    }
  };

  const handleImpersonate = (userId: number) => {
    router.post(`/users/${userId}/impersonate`);
  };

  const handleApproveKyc = (user: User) => {
    const kycId = user.kyc?.id;
    
    if (kycId) {
      // If KYC exists, approve it directly
      router.post(`/admin/kyc/${kycId}/approve`, {}, {
        preserveScroll: true,
        onSuccess: () => {
          // Success handled by flash message
        }
      });
    } else {
      // If no KYC exists, create one and approve it
      router.post('/admin/kyc/approve-user', { user_id: user.id }, {
        preserveScroll: true,
        onSuccess: () => {
          // Success handled by flash message
        }
      });
    }
  };

  const handleRejectKyc = (user: User) => {
    const kycId = user.kyc?.id;
    
    if (kycId) {
      router.post(`/admin/kyc/${kycId}/reject`, { 
        rejection_reason: 'Rejected by admin from users list.'
      }, {
        preserveScroll: true,
        onSuccess: () => {
          // Success handled by flash message
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

  const getRoleBadge = (user: User) => {
    const role = user.roles?.[0];
    if (!role) return <Badge variant="outline">No Role</Badge>;

    const colors: Record<string, string> = {
      admin: 'bg-purple-500',
      customer: 'bg-blue-500',
      agent: 'bg-gray-500',
    };

    return <Badge className={colors[role.slug] || 'bg-gray-500'}>{role.name}</Badge>;
  };

  const getKycBadge = (user: User) => {
    if (!user.kyc || !user.kyc.status) return null;

    const statusConfig: Record<string, { color: string; icon: JSX.Element; label: string }> = {
      approved: {
        color: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        icon: <ShieldCheck className="mr-1 h-3 w-3" />,
        label: 'Verified'
      },
      pending: {
        color: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        icon: <Shield className="mr-1 h-3 w-3" />,
        label: 'Pending'
      },
      rejected: {
        color: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        icon: <Shield className="mr-1 h-3 w-3" />,
        label: 'Rejected'
      },
    };

    const config = statusConfig[user.kyc.status] || {
      color: 'bg-gray-100 text-gray-800',
      icon: <Shield className="mr-1 h-3 w-3" />,
      label: user.kyc.status.charAt(0).toUpperCase() + user.kyc.status.slice(1)
    };

    return (
      <Badge 
        variant="secondary" 
        className={config.color}
      >
        {config.icon}
        {config.label}
      </Badge>
    );
  };

  return (
    <AppLayout
      breadcrumbs={[
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Users', href: '/users' }
      ]}
    >
      <Head title="Users Management" />

      <div className="mx-auto max-w-7xl space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <Heading title={t('users.title')} description={t('users.description')} />
          <div className="flex items-center gap-2">
            <PageHelp title="Users Help" sections={helpSections} />
            <Link href="/users/create">
              <Button>
                <Plus className="mr-2 h-4 w-4" />
                {t('users.add')}
              </Button>
            </Link>
          </div>
        </div>

        {/* Filters Card */}
        <Card>
          <CardHeader>
            <CardTitle>Filters</CardTitle>
            <CardDescription>Search and filter users</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 md:grid-cols-3">
              <div className="relative">
                <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder={t('users.search')}
                  value={search}
                  onChange={(e) => handleSearch(e.target.value)}
                  className="pl-9"
                />
              </div>

              <Select value={selectedRole} onValueChange={handleRoleFilter}>
                <SelectTrigger>
                  <SelectValue placeholder="Filter by role" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Roles</SelectItem>
                  {roles.map((role) => (
                    <SelectItem key={role.id} value={role.slug}>
                      {role.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>

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

        {/* Users Table */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle>All Users</CardTitle>
                <CardDescription>
                  Showing {users.from} to {users.to} of {users.total} users
                </CardDescription>
              </div>
            </div>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{t('common.name')}</TableHead>
                  <TableHead>{t('common.email')}</TableHead>
                  <TableHead>{t('users.role')}</TableHead>
                  <TableHead>{t('users.kyc_status')}</TableHead>
                  <TableHead>{t('common.status')}</TableHead>
                  <TableHead>{t('users.parent')}</TableHead>
                  <TableHead>{t('users.last_login')}</TableHead>
                  <TableHead className="text-right">{t('common.actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {users.data.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={8} className="text-center text-muted-foreground">
                      {t('users.no_users')}
                    </TableCell>
                  </TableRow>
                ) : (
                  users.data.map((user) => (
                    <TableRow key={user.id}>
                      <TableCell className="font-medium">
                        <div className="flex items-center gap-2">
                          <div className="h-8 w-8 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white text-sm font-semibold">
                            {user.name.charAt(0).toUpperCase()}
                          </div>
                          {user.name}
                        </div>
                      </TableCell>
                      <TableCell>{user.email}</TableCell>
                      <TableCell>{getRoleBadge(user)}</TableCell>
                      <TableCell>{getKycBadge(user)}</TableCell>
                      <TableCell>{getStatusBadge(user.status)}</TableCell>
                      <TableCell>
                        {user.parent ? (
                          <span className="text-sm text-muted-foreground">
                            {user.parent.name}
                          </span>
                        ) : (
                          <span className="text-sm text-muted-foreground">-</span>
                        )}
                      </TableCell>
                      <TableCell>
                        {user.last_login_at ? (
                          <span className="text-sm">
                            {new Date(user.last_login_at).toLocaleDateString()}
                          </span>
                        ) : (
                          <span className="text-sm text-muted-foreground">{t('users.never')}</span>
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
                              <Link href={`/users/${user.id}`}>
                                <Eye className="mr-2 h-4 w-4" />
                                {t('users.view_details')}
                              </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                              <Link href={`/users/${user.id}/edit`}>
                                <Edit className="mr-2 h-4 w-4" />
                                {t('common.edit')}
                              </Link>
                            </DropdownMenuItem>
                            {user.roles?.[0]?.slug === 'customer' && (
                              <>
                                {user.kyc?.status === 'approved' ? (
                                  <DropdownMenuItem onClick={() => handleRejectKyc(user)}>
                                    <ShieldX className="mr-2 h-4 w-4" />
                                    {t('users.reject_kyc')}
                                  </DropdownMenuItem>
                                ) : (
                                  <DropdownMenuItem onClick={() => handleApproveKyc(user)}>
                                    <Shield className="mr-2 h-4 w-4" />
                                    {t('users.approve_kyc')}
                                  </DropdownMenuItem>
                                )}
                              </>
                            )}
                            <DropdownMenuItem onClick={() => handleToggleStatus(user.id)}>
                              {user.status === 'active' ? (
                                <>
                                  <UserX className="mr-2 h-4 w-4" />
                                  {t('users.deactivate')}
                                </>
                              ) : (
                                <>
                                  <UserCheck className="mr-2 h-4 w-4" />
                                  {t('users.activate')}
                                </>
                              )}
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => handleImpersonate(user.id)}>
                              <UserCog className="mr-2 h-4 w-4" />
                              {t('users.impersonate')}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                              className="text-destructive"
                              onClick={() => setDeleteUserId(user.id)}
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
            {users.last_page > 1 && (
              <div className="mt-4 flex items-center justify-between">
                <p className="text-sm text-muted-foreground">
                  Page {users.current_page} of {users.last_page}
                </p>
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={users.current_page === 1}
                    onClick={() => router.get('/users', {
                      ...filters,
                      page: users.current_page - 1
                    })}
                  >
                    {t('common.previous')}
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={users.current_page === users.last_page}
                    onClick={() => router.get('/users', {
                      ...filters,
                      page: users.current_page + 1
                    })}
                  >
                    {t('common.next')}
                  </Button>
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Delete Confirmation Dialog */}
      <Dialog open={deleteUserId !== null} onOpenChange={() => setDeleteUserId(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('common.are_you_sure')}</DialogTitle>
            <DialogDescription>
              {t('users.delete_confirm')}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDeleteUserId(null)}>
              {t('common.cancel')}
            </Button>
            <Button onClick={handleDelete} variant="destructive">
              {t('common.delete')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </AppLayout>
  );
}
