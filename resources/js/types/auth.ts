export type User = {
    id: number;
    name: string;
    email: string;
    role_id: string | null;
    blocked_at: string | null;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    isAdmin: boolean;
    isSuperAdmin: boolean;
};
