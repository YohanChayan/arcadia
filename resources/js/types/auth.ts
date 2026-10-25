/** The signed-in account, as `HandleInertiaRequests` shares it. */
export type User = {
    name: string;
    email: string;
    email_verified_at: string | null;
    approved_at: string | null;
    role: { name: string; alias_number: number; alias: string } | null;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
