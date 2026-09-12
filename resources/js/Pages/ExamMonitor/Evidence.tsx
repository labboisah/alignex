import { useState } from 'react';

export function EvidenceImage({ url }: { url?: string | null }) {
    const [failed, setFailed] = useState(false);
    if (!url) return <span className="text-slate-500">No image captured</span>;
    if (failed) return <span role="status" className="text-sm text-slate-500">Image unavailable</span>;
    return <a href={url} target="_blank" rel="noreferrer" className="inline-block" aria-label="Open webcam evidence image">
        <img src={url} alt="Webcam evidence" loading="lazy" onError={() => setFailed(true)} className="h-24 w-32 rounded-md border border-border object-cover" />
        <span className="mt-1 block text-xs text-primary underline">View image</span>
    </a>;
}

function label(value: string): string {
    return value.replaceAll('_', ' ').replace(/([a-z])([A-Z])/g, '$1 $2').replace(/^./, character => character.toUpperCase());
}

function valueText(value: unknown): string {
    if (value === null || value === undefined || value === '') return 'Not provided';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (Array.isArray(value)) return value.map(valueText).join(', ');
    if (typeof value === 'object') return Object.entries(value).map(([key, item]) => label(key) + ': ' + valueText(item)).join('; ');
    return String(value);
}

export function EvidenceDetails({ payload }: { payload?: Record<string, unknown> | null }) {
    const entries = Object.entries(payload ?? {}).filter(([key]) => !['snapshot_path', 'snapshot_url', 'webcam_snapshot'].includes(key));
    if (!entries.length) return <span className="text-slate-500">No additional details</span>;
    return <dl className="min-w-48 max-w-md space-y-1 py-2">
        {entries.map(([key, value]) => <div key={key}><dt className="inline font-semibold">{label(key)}: </dt><dd className="inline break-words">{valueText(value)}</dd></div>)}
    </dl>;
}
