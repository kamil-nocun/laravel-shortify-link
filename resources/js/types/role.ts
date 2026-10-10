export type Permission = { key: string; label: string };

export type PermissionGroup = { group: string; permissions: Permission[] };

export type RoleSummary = {
    id: string;
    name: string;
    description: string | null;
    is_super: boolean;
    is_default: boolean;
    permissions_count: number;
    users_count: number;
};

export type Role = Omit<RoleSummary, 'permissions_count' | 'users_count'> & {
    permissions: string[];
};
