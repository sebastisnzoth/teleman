import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';

interface Permission {
    id: number;
    name: string;
    description: string;
}

interface Role {
    id: number;
    name: string;
    level: number;
    permissions: Permission[];
}

interface Props {
    role: Role;
    availablePermissions: Permission[];
}

export default function Permissions({ role, availablePermissions = [] }: Props) {
    const { t } = useTranslation();
    const roleData = role || { id: 0, name: '', level: 0, permissions: [] };
    const permissions = Array.isArray(availablePermissions) ? availablePermissions : [];
    const rolePermissions = Array.isArray(roleData.permissions) ? roleData.permissions : [];
    
    const { data, setData, put, processing } = useForm({
        permissions: rolePermissions.map((p: Permission) => p.id),
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/roles/${roleData.id}/permissions`);
    };

    const handlePermissionToggle = (permissionId: number) => {
        const currentPermissions = Array.isArray(data.permissions) ? data.permissions : [];
        if (currentPermissions.includes(permissionId)) {
            setData('permissions', currentPermissions.filter((id: number) => id !== permissionId));
        } else {
            setData('permissions', [...currentPermissions, permissionId]);
        }
    };

    return (
        <AppLayout>
            <Head title={`Permissions - ${roleData.name}`} />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Link href="/roles">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                    </Link>
                    <Heading
                        title={`Manage Permissions - ${roleData.name}`}
                        description={t('roles.description')}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Role Information</CardTitle>
                        <CardDescription>
                            Role: <strong>{roleData.name}</strong> (Level {roleData.level})
                        </CardDescription>
                    </CardHeader>
                </Card>

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('roles.permissions')}</CardTitle>
                            <CardDescription>
                                Select the permissions to grant to this role
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {permissions.length === 0 ? (
                                    <p className="text-muted-foreground">No permissions available</p>
                                ) : (
                                    permissions.map((permission) => (
                                        <div
                                            key={permission.id}
                                            className="flex items-start space-x-3 border-b pb-3 last:border-0"
                                        >
                                            <Checkbox
                                                id={`permission-${permission.id}`}
                                                checked={data.permissions.includes(permission.id)}
                                                onCheckedChange={() => handlePermissionToggle(permission.id)}
                                            />
                                            <div className="flex-1">
                                                <label
                                                    htmlFor={`permission-${permission.id}`}
                                                    className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                                                >
                                                    {permission.name}
                                                </label>
                                                {permission.description && (
                                                    <p className="text-sm text-muted-foreground mt-1">
                                                        {permission.description}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>

                            <div className="flex justify-end mt-6">
                                <Button type="submit" disabled={processing}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {t('common.save')}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
