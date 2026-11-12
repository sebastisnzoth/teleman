import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Shield, Users, Key } from 'lucide-react';

interface Permission {
    id: number;
    name: string;
    description: string;
}

interface Role {
    id: number;
    name: string;
    level: number;
    description: string;
    users_count: number;
    permissions: Permission[];
    created_at: string;
}

interface Props {
    role: Role;
}

export default function Show({ role }: Props) {
    const { t } = useTranslation();
    const roleData = role || {
        id: 0,
        name: '',
        level: 0,
        description: '',
        users_count: 0,
        permissions: [],
        created_at: ''
    };
    
    const permissions = Array.isArray(roleData.permissions) ? roleData.permissions : [];

    return (
        <AppLayout>
            <Head title={roleData.name} />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Link href="/roles">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                    </Link>
                    <Heading
                        title={roleData.name}
                        description={t('roles.description')}
                    />
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Role Level</CardTitle>
                            <Shield className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{roleData.level}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('roles.users_count')}</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {Number(roleData.users_count || 0)}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('roles.permissions')}</CardTitle>
                            <Key className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{permissions.length}</div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Role Information</CardTitle>
                        <CardDescription>{roleData.description || 'No description available'}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div>
                                <p className="text-sm text-muted-foreground">Created Date</p>
                                <p className="font-medium">
                                    {new Date(roleData.created_at).toLocaleDateString()}
                                </p>
                            </div>
                            
                            <div className="flex gap-2 mt-4">
                                <Button variant="outline" asChild>
                                    <Link href={`/roles/${roleData.id}/permissions`}>
                                        <Key className="mr-2 h-4 w-4" />
                                        Manage Permissions
                                    </Link>
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={`/roles/${roleData.id}/edit`}>
                                        {t('roles.edit')}
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('roles.permissions')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {permissions.length === 0 ? (
                            <p className="text-muted-foreground">No permissions assigned to this role</p>
                        ) : (
                            <div className="grid gap-3 md:grid-cols-2">
                                {permissions.map((permission) => (
                                    <div
                                        key={permission.id}
                                        className="flex items-start gap-3 p-3 border rounded-lg"
                                    >
                                        <Badge variant="outline">{permission.name}</Badge>
                                        {permission.description && (
                                            <p className="text-sm text-muted-foreground flex-1">
                                                {permission.description}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
