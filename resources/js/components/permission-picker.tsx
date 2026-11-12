import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import type { Permission } from '@/types';

interface PermissionPickerProps {
  permissions: Permission[];
  selectedPermissions: number[];
  onChange: (permissionIds: number[]) => void;
  disabled?: boolean;
}

export default function PermissionPicker({
  permissions,
  selectedPermissions,
  onChange,
  disabled = false,
}: PermissionPickerProps) {
  // Group permissions by module
  const permissionsByModule = permissions.reduce((acc, permission) => {
    if (!acc[permission.module]) {
      acc[permission.module] = [];
    }
    acc[permission.module].push(permission);
    return acc;
  }, {} as Record<string, Permission[]>);

  const modules = Object.keys(permissionsByModule).sort();

  const handlePermissionToggle = (permissionId: number) => {
    if (selectedPermissions.includes(permissionId)) {
      onChange(selectedPermissions.filter((id) => id !== permissionId));
    } else {
      onChange([...selectedPermissions, permissionId]);
    }
  };

  const handleModuleToggle = (module: string) => {
    const modulePermissions = permissionsByModule[module];
    const modulePermissionIds = modulePermissions.map((p) => p.id);
    const allSelected = modulePermissionIds.every((id) =>
      selectedPermissions.includes(id)
    );

    if (allSelected) {
      // Deselect all module permissions
      onChange(
        selectedPermissions.filter((id) => !modulePermissionIds.includes(id))
      );
    } else {
      // Select all module permissions
      const newPermissions = [
        ...selectedPermissions,
        ...modulePermissionIds.filter((id) => !selectedPermissions.includes(id)),
      ];
      onChange(newPermissions);
    }
  };

  const isModuleFullySelected = (module: string) => {
    const modulePermissions = permissionsByModule[module];
    return modulePermissions.every((p) => selectedPermissions.includes(p.id));
  };

  const isModulePartiallySelected = (module: string) => {
    const modulePermissions = permissionsByModule[module];
    return (
      modulePermissions.some((p) => selectedPermissions.includes(p.id)) &&
      !isModuleFullySelected(module)
    );
  };

  const getModuleColor = (module: string): string => {
    const colors: Record<string, string> = {
      campaigns: 'bg-blue-500',
      contacts: 'bg-green-500',
      calls: 'bg-purple-500',
      analytics: 'bg-orange-500',
      agents: 'bg-pink-500',
      users: 'bg-cyan-500',
      settings: 'bg-yellow-500',
      billing: 'bg-red-500',
    };
    return colors[module] || 'bg-gray-500';
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-lg font-medium">Permissions</h3>
          <p className="text-sm text-muted-foreground">
            Select permissions for this agent ({selectedPermissions.length} selected)
          </p>
        </div>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        {modules.map((module) => {
          const modulePermissions = permissionsByModule[module];
          const isFullySelected = isModuleFullySelected(module);
          const isPartiallySelected = isModulePartiallySelected(module);

          return (
            <Card key={module}>
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Checkbox
                      id={`module-${module}`}
                      checked={isFullySelected}
                      onCheckedChange={() => handleModuleToggle(module)}
                      disabled={disabled}
                      className={isPartiallySelected ? 'data-[state=checked]:bg-primary/50' : ''}
                    />
                    <Label
                      htmlFor={`module-${module}`}
                      className="cursor-pointer flex items-center gap-2"
                    >
                      <Badge className={`${getModuleColor(module)} capitalize`}>
                        {module}
                      </Badge>
                      <span className="text-sm text-muted-foreground">
                        ({modulePermissions.filter(p => selectedPermissions.includes(p.id)).length}/{modulePermissions.length})
                      </span>
                    </Label>
                  </div>
                </div>
              </CardHeader>
              <CardContent className="pt-0">
                <div className="space-y-2">
                  {modulePermissions.map((permission) => (
                    <div key={permission.id} className="flex items-start gap-2">
                      <Checkbox
                        id={`permission-${permission.id}`}
                        checked={selectedPermissions.includes(permission.id)}
                        onCheckedChange={() => handlePermissionToggle(permission.id)}
                        disabled={disabled}
                        className="mt-0.5"
                      />
                      <div className="flex-1">
                        <Label
                          htmlFor={`permission-${permission.id}`}
                          className="cursor-pointer text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                        >
                          {permission.name}
                        </Label>
                        {permission.description && (
                          <p className="text-xs text-muted-foreground mt-1">
                            {permission.description}
                          </p>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>
    </div>
  );
}
