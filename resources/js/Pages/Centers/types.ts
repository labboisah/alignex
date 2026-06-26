export type Center = Record<string, unknown> & {
    id: number;
    name: string;
    code: string;
    location: string;
    capacity: number;
    contact_person: string;
    phone?: string | null;
    email: string;
    status: 'active' | 'inactive';
    status_label: string;
};

export type StatusOption = {
    value: string;
    label: string;
};
