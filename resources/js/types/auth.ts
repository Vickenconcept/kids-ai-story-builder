export type User = {
    id: number;
    uuid: string;
    name: string;
    email: string;
    avatar?: string;
    story_credits?: number;
    feature_tier?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    canManageCreditPacks?: boolean;
    canManageUsers?: boolean;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
