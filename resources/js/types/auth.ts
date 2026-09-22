export type UserRole = {
    name: string;
    slug: string;
};

export type UserStaff = {
    id: number;
    staff_number: string;
    full_name: string;
};

export type User = {
    id: number;
    username: string;
    name: string;
    first_name: string | null;
    last_name: string | null;
    email: string | null;
    avatar?: string;
    must_reset_password: boolean;
    two_factor_enabled?: boolean;
    role: UserRole | null;
    staff: UserStaff | null;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    /** Permission names the user holds, or ['*'] for an administrator. UI hint only; the server enforces access. */
    permissions: string[];
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
