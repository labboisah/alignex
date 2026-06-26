export type Organization = Record<string, unknown> & {
    id: number;
    name: string;
    code: string;
    contact_person: string;
    email: string;
    phone?: string | null;
    address?: string | null;
    status: 'active' | 'inactive';
    status_label: string;
};

export type StatusOption = {
    value: string;
    label: string;
};
