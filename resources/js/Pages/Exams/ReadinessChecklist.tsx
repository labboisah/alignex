import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/Components/ui/button';

export type ExamReadiness = {
    ready: boolean;
    checks: { id: string; label: string; ready: boolean; message: string; href: string }[];
    device_requirements: { fullscreen: boolean; camera: boolean };
};

export function ReadinessChecklist({ readiness, editable, examId }: { readiness: ExamReadiness; editable: boolean; examId: string }) {
    const [refreshing, setRefreshing] = useState(false);
    const [error, setError] = useState('');
    return <section aria-label="Exam readiness" className="mb-5 rounded-md border border-border bg-white p-5 shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 className="font-semibold text-slate-900">{readiness.ready ? 'Ready to schedule or activate' : 'Complete setup before publishing'}</h2>
                <p className="mt-1 text-sm text-slate-600">{readiness.checks.filter(check => check.ready).length} of {readiness.checks.length} required checks passed.</p>
            </div>
            <Button type="button" variant="secondary" disabled={refreshing} onClick={() => {
                setRefreshing(true); setError('');
                router.reload({ only: ['readiness'], onError: () => setError('Could not refresh readiness. Try again.'), onFinish: () => setRefreshing(false) });
            }}>{refreshing ? 'Checking...' : 'Check again'}</Button>
        </div>
        {error && <p role="alert" className="mt-3 text-sm text-danger">{error}</p>}
        <ul className="mt-4 divide-y divide-border">
            {readiness.checks.map(check => <li key={check.id} className="flex items-start justify-between gap-4 py-3">
                <div><p className={check.ready ? 'font-semibold text-green-700' : 'font-semibold text-amber-800'}>{check.ready ? 'Passed' : 'Action needed'}: {check.label}</p><p className="mt-1 text-sm text-slate-600">{check.message}</p></div>
                {!check.ready && editable && <Link className="shrink-0 text-sm font-semibold text-primary underline" href={check.href}>Fix this</Link>}
            </li>)}
        </ul>
        <div className="mt-3 rounded-md bg-slate-50 p-3 text-sm text-slate-700">
            <p className="font-semibold">Candidate device requirements</p>
            <p>Fullscreen: {readiness.device_requirements.fullscreen ? 'Required ? candidates need a browser that supports fullscreen.' : 'Not required.'}</p>
            <p>Camera: {readiness.device_requirements.camera ? 'Required ? candidates must grant camera access.' : 'Not required.'}</p>
            <p className="mt-1">Device permissions are checked on the candidate device when starting the exam.</p>
        </div>
        {editable && readiness.ready && <div className="mt-4"><Button asChild><Link href={'/exams/' + examId + '/edit'}>Review status and schedule</Link></Button></div>}
    </section>;
}
