export type EntityType = {
    value: 'organization' | 'school' | 'center';
    label: string;
    description: string;
};

export type AdminRegistration = Record<string, unknown> & {
    id: number;
    entity_type: 'organization' | 'school' | 'center';
    entity_type_label: string;
    entity_id?: number | null;
    admin_name: string;
    admin_email: string;
    entity_name: string;
    entity_code: string;
    location?: string | null;
    capacity?: number | null;
    contact_person: string;
    phone?: string | null;
    entity_email: string;
    address?: string | null;
    legal_registration_number?: string | null;
    website?: string | null;
    years_in_operation?: number | null;
    operating_scope?: string | null;
    accreditation_body?: string | null;
    accreditation_number?: string | null;
    facility_summary?: string | null;
    exam_experience?: string | null;
    expected_candidates?: number | null;
    status: 'pending' | 'approved' | 'rejected' | 'deactivated';
    status_label: string;
    review_notes?: string | null;
    created_at?: string | null;
};
