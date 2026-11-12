import { usePage } from '@inertiajs/react';
import type { PageProps } from '@inertiajs/core';
import type { User, Role, Permission } from '@/types';

interface AuthPageProps extends PageProps {
    auth: {
        user: User;
    };
}

/**
 * Custom hook to access authentication state and permission checking
 */
export function useAuth() {
    const { auth } = usePage<AuthPageProps>().props;
    const user = auth.user;

    /**
     * Check if user has a specific role
     */
    const hasRole = (roleName: string | string[]): boolean => {
        if (!user.roles || user.roles.length === 0) return false;

        const roles = Array.isArray(roleName) ? roleName : [roleName];
        return user.roles.some((role: Role) => 
            roles.includes(role.name) || roles.includes(role.slug)
        );
    };

    /**
     * Check if user has a specific permission
     */
    const hasPermission = (permissionSlug: string | string[]): boolean => {
        if (!user.permissions || user.permissions.length === 0) return false;

        const permissions = Array.isArray(permissionSlug) ? permissionSlug : [permissionSlug];
        return user.permissions.some((permission: Permission) =>
            permissions.includes(permission.slug)
        );
    };

    /**
     * Check if user has any of the specified permissions
     */
    const hasAnyPermission = (permissions: string[]): boolean => {
        return permissions.some(permission => hasPermission(permission));
    };

    /**
     * Check if user has all of the specified permissions
     */
    const hasAllPermissions = (permissions: string[]): boolean => {
        return permissions.every(permission => hasPermission(permission));
    };

    /**
     * Check if user is an Admin
     */
    const isAdmin = (): boolean => {
        return hasRole('Admin') || hasRole('admin');
    };

    /**
     * Check if user is a Customer
     */
    const isCustomer = (): boolean => {
        return hasRole('Customer') || hasRole('customer');
    };

    /**
     * Check if user is an Agent
     */
    const isAgent = (): boolean => {
        return hasRole('Agent') || hasRole('agent');
    };

    /**
     * Get user's primary role
     */
    const getPrimaryRole = (): Role | null => {
        if (!user.roles || user.roles.length === 0) return null;
        return user.roles[0]; // Assuming first role is primary
    };

    /**
     * Get role level (1 = Admin, 2 = Customer, 3 = Agent)
     */
    const getRoleLevel = (): number => {
        const role = getPrimaryRole();
        return role?.level || 999;
    };

    /**
     * Check if user can access a module
     */
    const canAccessModule = (module: string): boolean => {
        if (isAdmin()) return true;
        
        if (!user.permissions) return false;
        
        return user.permissions.some((permission: Permission) =>
            permission.module.toLowerCase() === module.toLowerCase()
        );
    };

    return {
        user,
        hasRole,
        hasPermission,
        hasAnyPermission,
        hasAllPermissions,
        isAdmin,
        isCustomer,
        isAgent,
        getPrimaryRole,
        getRoleLevel,
        canAccessModule,
    };
}

/**
 * Permission gate utilities
 */
export const can = {
    /**
     * Check campaign permissions
     */
    viewCampaigns: (user: User): boolean => {
        return checkPermission(user, 'campaigns.view');
    },
    createCampaigns: (user: User): boolean => {
        return checkPermission(user, 'campaigns.create');
    },
    editCampaigns: (user: User): boolean => {
        return checkPermission(user, 'campaigns.edit');
    },
    deleteCampaigns: (user: User): boolean => {
        return checkPermission(user, 'campaigns.delete');
    },
    launchCampaigns: (user: User): boolean => {
        return checkPermission(user, 'campaigns.launch');
    },

    /**
     * Check contact permissions
     */
    viewContacts: (user: User): boolean => {
        return checkPermission(user, 'contacts.view');
    },
    createContacts: (user: User): boolean => {
        return checkPermission(user, 'contacts.create');
    },
    editContacts: (user: User): boolean => {
        return checkPermission(user, 'contacts.edit');
    },
    deleteContacts: (user: User): boolean => {
        return checkPermission(user, 'contacts.delete');
    },
    importContacts: (user: User): boolean => {
        return checkPermission(user, 'contacts.import');
    },
    exportContacts: (user: User): boolean => {
        return checkPermission(user, 'contacts.export');
    },

    /**
     * Check call permissions
     */
    viewCalls: (user: User): boolean => {
        return checkPermission(user, 'calls.view');
    },
    initiateCalls: (user: User): boolean => {
        return checkPermission(user, 'calls.initiate');
    },
    endCalls: (user: User): boolean => {
        return checkPermission(user, 'calls.end');
    },
    viewRecordings: (user: User): boolean => {
        return checkPermission(user, 'calls.view-recordings');
    },
    downloadRecordings: (user: User): boolean => {
        return checkPermission(user, 'calls.download-recordings');
    },

    /**
     * Check analytics permissions
     */
    viewAnalytics: (user: User): boolean => {
        return checkPermission(user, 'analytics.view');
    },
    exportReports: (user: User): boolean => {
        return checkPermission(user, 'analytics.export');
    },

    /**
     * Check user management permissions
     */
    viewUsers: (user: User): boolean => {
        return checkPermission(user, 'users.view');
    },
    createUsers: (user: User): boolean => {
        return checkPermission(user, 'users.create');
    },
    editUsers: (user: User): boolean => {
        return checkPermission(user, 'users.edit');
    },
    deleteUsers: (user: User): boolean => {
        return checkPermission(user, 'users.delete');
    },

    /**
     * Check settings permissions
     */
    viewSettings: (user: User): boolean => {
        return checkPermission(user, 'settings.view');
    },
    editSettings: (user: User): boolean => {
        return checkPermission(user, 'settings.edit');
    },
};

/**
 * Helper function to check a specific permission
 */
function checkPermission(user: User, permissionSlug: string): boolean {
    // Admin has all permissions
    if (user.roles?.some((role: Role) => role.slug === 'admin' || role.name === 'Admin')) {
        return true;
    }

    // Check if user has the specific permission
    return user.permissions?.some((permission: Permission) => 
        permission.slug === permissionSlug
    ) || false;
}

/**
 * Check if user owns a resource
 */
export function ownsResource(user: User, resource: { user_id: number }): boolean {
    return user.id === resource.user_id;
}

/**
 * Check if user can access parent's resources (for agents)
 */
export function canAccessParentResource(user: User, resource: { user_id: number }): boolean {
    if (ownsResource(user, resource)) return true;
    
    // Agents can access their parent's resources
    if (user.parent_user_id && user.parent_user_id === resource.user_id) {
        return true;
    }

    return false;
}
