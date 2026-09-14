import * as Dialog from '@radix-ui/react-dialog';
import { useForm } from '@inertiajs/react';
import { CalendarClock, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { useToast } from '@/Components/ui/toast';

export type RetakeCandidate = {
    attempt_id: string;
    candidate_name: string;
    registration_number: string;
    attempt_number: number;
    pending_id: string | null;
    pending_status: string | null;
    starts_at: string | null;
    ends_at: string | null;
    can_cancel: boolean;
};

export default function RetakeManager({ candidates, duration }: { candidates: RetakeCandidate[]; duration: number }) {
    const [selected, setSelected] = useState<RetakeCandidate | null>(null);
    const { showToast } = useToast();
    const form = useForm({ starts_at: '', ends_at: '', duration_minutes: duration, reason: '' });
    const cancellation = useForm({});
    const errors = form.errors as Record<string, string>;

    return (
        <section className="my-6 rounded-md border border-border bg-white p-5 shadow-sm">
            <h2 className="font-semibold text-slateDark">Candidate retakes</h2>
            <p className="mt-1 text-sm text-slate-500">Schedule an online retake for a candidate with a closed attempt. The new completed score replaces the current result, even if it is lower. Previous attempts remain in history.</p>
            {candidates.length === 0 ? <p className="mt-4 text-sm text-slate-500">No candidates are eligible for a retake.</p> : (
                <div className="mt-4 overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead><tr><th className="py-2">Candidate</th><th>Latest attempt</th><th>Retake schedule</th><th>Action</th></tr></thead>
                        <tbody className="divide-y divide-border">
                            {candidates.map(candidate => <tr key={candidate.attempt_id}>
                                <td className="py-3"><span className="font-semibold">{candidate.candidate_name}</span><br />{candidate.registration_number}</td>
                                <td>Attempt {candidate.attempt_number}</td>
                                <td>{candidate.pending_id ? <><span>{candidate.pending_status === 'in_progress' ? 'In progress' : 'Scheduled'}</span><br />{candidate.starts_at && new Date(candidate.starts_at).toLocaleString()}<br />{candidate.ends_at && 'Closes ' + new Date(candidate.ends_at).toLocaleString()}</> : 'No pending retake'}</td>
                                <td>{!candidate.pending_id ? (
                                    <Button size="sm" variant="secondary" onClick={() => { form.reset(); form.clearErrors(); setSelected(candidate); }}><CalendarClock className="h-4 w-4" />Schedule retake</Button>
                                ) : candidate.can_cancel && (
                                    <Button size="sm" variant="secondary" disabled={cancellation.processing} onClick={() => cancellation.post(`/exams/attempts/${candidate.pending_id}/retake/cancel`, {
                                        preserveScroll: true,
                                        onSuccess: () => showToast({ title: 'Retake cancelled' }),
                                        onError: () => showToast({ title: 'Could not cancel retake', description: 'Review the error below.' }),
                                    })}>{cancellation.processing ? 'Cancelling...' : 'Cancel retake'}</Button>
                                )}</td>
                            </tr>)}
                        </tbody>
                    </table>
                </div>
            )}
            {Object.values(cancellation.errors).map((error, index) => <p key={index} role="alert" className="mt-3 text-sm text-danger">{error}</p>)}
            <Dialog.Root open={selected !== null} onOpenChange={open => { if (!open && !form.processing) setSelected(null); }}>
                <Dialog.Portal>
                    <Dialog.Overlay className="fixed inset-0 z-50 bg-slate-950/50" />
                    <Dialog.Content className="fixed left-1/2 top-1/2 z-50 max-h-[90vh] w-[calc(100%_-_2rem)] max-w-lg -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                        <Dialog.Title className="text-lg font-semibold">Schedule retake</Dialog.Title>
                        <Dialog.Description className="mt-2 text-sm text-slate-600">For {selected?.candidate_name} ({selected?.registration_number}). Times use your device timezone ({Intl.DateTimeFormat().resolvedOptions().timeZone}). The closing time is a hard deadline.</Dialog.Description>
                        <Dialog.Close asChild><button type="button" aria-label="Close" disabled={form.processing} className="absolute right-3 top-3 rounded p-1"><X className="h-4 w-4" /></button></Dialog.Close>
                        <form className="mt-5 space-y-4" onSubmit={event => {
                            event.preventDefault();
                            if (!selected) return;
                            form.transform(data => ({ ...data, starts_at: new Date(data.starts_at).toISOString(), ends_at: new Date(data.ends_at).toISOString() }));
                            form.post(`/exams/attempts/${selected.attempt_id}/retake`, {
                                preserveScroll: true,
                                onSuccess: () => { setSelected(null); showToast({ title: 'Retake scheduled', description: 'The existing result stays current until the retake is completed.' }); },
                                onError: () => showToast({ title: 'Could not schedule retake', description: 'Review the highlighted errors.' }),
                            });
                        }}>
                            <label className="block text-sm font-medium">Start date and time
                                <input required type="datetime-local" className="mt-1 block w-full rounded-md border-border" value={form.data.starts_at} onChange={event => form.setData('starts_at', event.target.value)} />
                                {errors.starts_at && <span role="alert" className="text-danger">{errors.starts_at}</span>}
                            </label>
                            <label className="block text-sm font-medium">Closing date and time
                                <input required type="datetime-local" className="mt-1 block w-full rounded-md border-border" value={form.data.ends_at} onChange={event => form.setData('ends_at', event.target.value)} />
                                {errors.ends_at && <span role="alert" className="text-danger">{errors.ends_at}</span>}
                            </label>
                            <label className="block text-sm font-medium">Duration in minutes
                                <input required type="number" min="1" max="1440" className="mt-1 block w-full rounded-md border-border" value={form.data.duration_minutes} onChange={event => form.setData('duration_minutes', Number(event.target.value))} />
                                {errors.duration_minutes && <span role="alert" className="text-danger">{errors.duration_minutes}</span>}
                            </label>
                            <label className="block text-sm font-medium">Reason
                                <textarea required maxLength={2000} rows={3} className="mt-1 block w-full rounded-md border-border" value={form.data.reason} onChange={event => form.setData('reason', event.target.value)} />
                                {errors.reason && <span role="alert" className="text-danger">{errors.reason}</span>}
                            </label>
                            {errors.retake && <p role="alert" className="text-sm text-danger">{errors.retake}</p>}
                            <p className="text-sm text-slate-500">The candidate uses the same exam code and registration number. Their previous paper is reused with empty answers and a fresh timer.</p>
                            <Button type="submit" disabled={form.processing}>{form.processing ? 'Scheduling...' : 'Schedule retake'}</Button>
                        </form>
                    </Dialog.Content>
                </Dialog.Portal>
            </Dialog.Root>
        </section>
    );
}
