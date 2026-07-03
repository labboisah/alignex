import { Head, Link } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';
import { StatusBadge } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

type Certificate = {
    serial_number: string;
    verification_hash: string;
    status: string;
    candidate_name: string;
    registration_number: string;
    exam_title: string;
    exam_code: string;
    score: number;
    total_marks: number;
    percentage: number;
    institution_name?: string | null;
    logo_url?: string | null;
    primary_color?: string | null;
    accent_color?: string | null;
    background_color?: string | null;
    use_logo_watermark?: boolean;
    theme?: string | null;
    paper_size?: string | null;
    orientation?: string | null;
    template_key?: string | null;
    issued_at?: string | null;
    expires_at?: string | null;
};

export default function VerifyCertificate() {
    const initialSerial = new URLSearchParams(window.location.search).get('serial') ?? '';
    const [identifier, setIdentifier] = useState(initialSerial);
    const [certificate, setCertificate] = useState<Certificate | null>(null);
    const [valid, setValid] = useState<boolean | null>(null);
    const [loading, setLoading] = useState(false);

    const verify = async (event?: FormEvent<HTMLFormElement>) => {
        event?.preventDefault();
        if (!identifier.trim()) return;
        setLoading(true);
        setValid(null);
        const response = await fetch('/api/certificates/verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ identifier }),
        });
        const payload = await response.json();
        setValid(Boolean(payload.valid));
        setCertificate(payload.certificate ?? null);
        setLoading(false);
    };

    useEffect(() => {
        if (initialSerial) {
            void verify();
        }
    }, []);

    return (
        <main className="min-h-screen bg-lightBg px-4 py-10 text-slateDark">
            <Head title="Verify Certificate" />
            <section className="mx-auto max-w-3xl">
                <div className="mb-8 flex items-center justify-between gap-3">
                    <Link href="/" className="inline-flex items-center">
                        <img src="/images/logo.png" alt="AlignEx" className="h-12 w-12 object-contain" />
                    </Link>
                    <Link href="/exam/login" className="text-sm font-semibold text-slate-600">Candidate Exam</Link>
                </div>
                <div className="rounded-md border border-border bg-white p-6 shadow-sm">
                    <div className="text-sm font-semibold uppercase text-primary">Certificate Verification</div>
                    <h1 className="mt-2 text-3xl font-bold">Verify certificate</h1>
                    <form onSubmit={verify} className="mt-6 flex flex-col gap-3 sm:flex-row">
                        <input className="h-11 flex-1 rounded-md border-border shadow-sm focus:border-primary focus:ring-primary" value={identifier} onChange={(event) => setIdentifier(event.target.value)} placeholder="Serial number or verification hash" />
                        <Button type="submit" disabled={loading || !identifier.trim()}><Search className="h-4 w-4" />Verify</Button>
                    </form>
                    {valid === false && <div className="mt-5 rounded-md border border-red-200 bg-red-50 p-4 text-sm font-semibold text-danger">Certificate was not found or is not valid.</div>}
                    {certificate && (
                        <div className="mt-6">
                            <div className="mb-3 flex justify-end">
                                <Button type="button" variant="secondary" onClick={() => window.print()}><Download className="h-4 w-4" />Download / Print</Button>
                            </div>
                        <div className="overflow-hidden rounded-md border bg-white p-2" style={{ borderColor: certificate.primary_color ?? '#0F7A3A', backgroundColor: certificate.background_color ?? '#FFFFFF' }}>
                            <div className="relative border p-5" style={{ borderColor: certificate.accent_color ?? '#F59E0B' }}>
                                {(certificate.logo_url ?? '/images/logo.png') && certificate.use_logo_watermark !== false && (
                                    <img src={certificate.logo_url ?? '/images/logo.png'} alt="" className="pointer-events-none absolute left-1/2 top-1/2 h-52 w-52 -translate-x-1/2 -translate-y-1/2 object-contain opacity-10" />
                                )}
                                <div className="relative">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div className="flex items-center gap-3">
                                            <img src={certificate.logo_url ?? '/images/logo.png'} alt={certificate.institution_name ?? 'AlignEx'} className="h-12 w-12 object-contain" />
                                            <div>
                                                <div className="text-sm font-semibold uppercase" style={{ color: certificate.primary_color ?? '#0F7A3A' }}>{certificate.institution_name ?? 'AlignEx'}</div>
                                                <h2 className="mt-1 text-xl font-bold">{certificate.candidate_name}</h2>
                                            </div>
                                        </div>
                                        <StatusBadge label={certificate.status} tone={certificate.status === 'issued' ? 'success' : 'danger'} />
                                    </div>
                                    <div className="mt-5 rounded-md bg-white/80 p-4">
                                        <div className="mb-3 text-sm font-semibold uppercase text-success">Verified Certificate</div>
                                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                            <Info label="Serial Number" value={certificate.serial_number} />
                                            <Info label="Registration Number" value={certificate.registration_number} />
                                            <Info label="Exam" value={`${certificate.exam_title} (${certificate.exam_code})`} />
                                            <Info label="Score" value={`${certificate.score}/${certificate.total_marks} (${certificate.percentage}%)`} />
                                            <Info label="Issued At" value={certificate.issued_at ? new Date(certificate.issued_at).toLocaleString() : 'N/A'} />
                                            <Info label="Expires At" value={certificate.expires_at ? new Date(certificate.expires_at).toLocaleString() : 'No expiry'} />
                                            <Info label="Verification Hash" value={certificate.verification_hash} wide />
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>
                    )}
                </div>
            </section>
        </main>
    );
}

function Info({ label, value, wide = false }: { label: string; value: string; wide?: boolean }) {
    return (
        <div className={wide ? 'sm:col-span-2' : ''}>
            <dt className="font-semibold text-slate-500">{label}</dt>
            <dd className="mt-1 break-all font-bold text-slateDark">{value}</dd>
        </div>
    );
}
