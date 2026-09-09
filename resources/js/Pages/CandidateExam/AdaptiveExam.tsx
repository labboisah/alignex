import { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button } from '@/Components/ui/button';
import { ConfirmDialog } from '@/Components/Platform/ConfirmDialog';
import AdaptiveImprovementChart from './AdaptiveImprovementChart';

type Item = { question_id: string; question_text: string; question_type: string; options: { id: string; label: string; option_text: string }[] };
export type AdaptivePayload = {
    delivery_mode: 'adaptive'; candidate: { full_name: string; registration_number: string };
    exam: { title: string; instructions: string; settings: { require_fullscreen: boolean; require_webcam: boolean; monitor_screenshots: boolean } };
    attempt: { id: string; status: string }; level: number; is_practice: boolean; state_version: number;
    current_item: Item | null; selected_option_ids: string[]; remaining_time: number; committed_questions: number; total_questions: number; is_last_question: boolean;
    submitted: boolean; stop_reason: string | null; can_start: boolean; starts_in_seconds: number; exam_token?: string;
    recovery: { can_start: boolean; can_practice: boolean; penalty_percent: number; message: string; available_at: string | null };
    learning_progress?: {earned_marks:string;original_marks:string;remaining_marks:string;levels:{number:number;score:string;available_marks:string;penalty_marks:string}[];areas:{name:string;status:string}[]} | null;
    result: { score: string; total_marks: string } | null;
};
type Pending = { path: string; body: Record<string, unknown>; token: string; attemptId: string };
type Session = { token: string; payload: AdaptivePayload; pending?: Pending };
const sessionKey = 'alignex_adaptive_session';

export function initializeAdaptiveSession(payload: AdaptivePayload) {
    localStorage.setItem(sessionKey, JSON.stringify({ token: payload.exam_token, payload }));
}
export function clearAdaptiveSession() { localStorage.removeItem(sessionKey); }
function session(): Session {
    const saved = localStorage.getItem(sessionKey);
    if (saved) return JSON.parse(saved);
    const payload = JSON.parse(localStorage.getItem('alignex_exam_payload') || 'null');
    if (!payload || payload.delivery_mode !== 'adaptive') throw new Error('Please log in again.');
    return { payload, token: localStorage.getItem('alignex_exam_token') || '' };
}
function operationId() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();
    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    return Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
}
function fingerprint() { return localStorage.getItem('alignex_device_fingerprint') || ''; }
function persist(value: Session) {
    // The envelope is authoritative: token, level state and pending operation move together.
    localStorage.setItem(sessionKey, JSON.stringify(value));
    localStorage.setItem('alignex_exam_token', value.token);
    localStorage.setItem('alignex_exam_payload', JSON.stringify(value.payload));
}
async function request(path: string, token: string, body?: Record<string, unknown>) {
    const response = await fetch(path, {
        method: body ? 'POST' : 'GET', headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: 'Bearer ' + token },
        ...(body ? { body: JSON.stringify(body) } : {}),
    });
    const data = await response.json();
    if (!response.ok) {
        const error = new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'The request could not be completed.') as Error & { status?: number };
        error.status = response.status;
        throw error;
    }
    return data;
}
function clock(seconds: number) {
    const value = Math.max(0, seconds);
    return Math.floor(value / 60) + ':' + String(value % 60).padStart(2, '0');
}

export default function AdaptiveExam() {
    const navigate = useNavigate();
    const [payload, setPayload] = useState<AdaptivePayload | null>(null);
    const [selected, setSelected] = useState<string[]>([]);
    const [busy, setBusy] = useState(false);
    const [pending, setPending] = useState<Pending | undefined>();
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const [remaining, setRemaining] = useState(0);
    const [otherTab, setOtherTab] = useState(false);
    const [controlsReady, setControlsReady] = useState(false);
    const deadline = useRef(0);
    const busyRef = useRef(false);
    const dirty = useRef(false);
    const payloadRef = useRef<AdaptivePayload | null>(null);
    const stream = useRef<MediaStream | null>(null);
    const video = useRef<HTMLVideoElement | null>(null);
    const heading = useRef<HTMLHeadingElement | null>(null);
    const errorAlert = useRef<HTMLDivElement | null>(null);
    useEffect(() => { if (error) errorAlert.current?.focus(); }, [error]);
    const expiryRequested = useRef(false);

    const accept = useCallback((next: AdaptivePayload) => {
        const previous = payloadRef.current;
        const changed = previous?.current_item?.question_id !== next.current_item?.question_id || previous?.attempt.id !== next.attempt.id;
        if (changed || !dirty.current || previous?.state_version !== next.state_version) {
            setSelected(next.selected_option_ids || []); dirty.current = false;
        }
        payloadRef.current = next; setPayload(next);
        deadline.current = Date.now() + next.remaining_time * 1000;
        setRemaining(next.remaining_time); expiryRequested.current = false;
        setControlsReady(!next.exam.settings.require_fullscreen && !next.exam.settings.require_webcam
            || (!next.exam.settings.require_fullscreen || Boolean(document.fullscreenElement)) && (!next.exam.settings.require_webcam || Boolean(stream.current?.active)));
        const path = next.attempt.status === 'disqualified' ? '/exam/disqualified' : next.submitted ? '/exam/submitted' : next.attempt.status === 'in_progress' ? '/exam/write' : '/exam/instructions';
        navigate(path, { replace: true });
        if (changed) setTimeout(() => heading.current?.focus(), 0);
    }, [navigate]);

    const refresh = useCallback(async () => {
        if (busyRef.current) return;
        try {
            const saved = session();
            const next = await request('/api/candidate/exam?device_fingerprint=' + encodeURIComponent(fingerprint()), saved.token);
            const current = session();
            // Ignore an old read if another tab or a mutation has changed the session.
            if (current.token !== saved.token || current.payload.state_version !== saved.payload.state_version || busyRef.current) return;
            persist({ ...current, payload: next }); setPending(current.pending);
            accept(next); setOtherTab(false);
        } catch (e) { setError(e instanceof Error ? e.message : 'Unable to restore this exam.'); }
    }, [accept]);

    const send = useCallback(async (operation: Pending) => {
        if (busyRef.current) return;
        busyRef.current = true; setBusy(true); setError(''); setNotice('');
        try {
            const next: AdaptivePayload = await request(operation.path, operation.token, operation.body);
            const saved = session();
            // A different level installed by another tab must never be replaced by an older response.
            if ((saved.token !== operation.token && saved.payload.attempt.id !== next.attempt.id)
                || (saved.payload.attempt.id === next.attempt.id && saved.payload.state_version > next.state_version)) {
                setOtherTab(true); return;
            }
            persist({ token: next.exam_token || operation.token, payload: next });
            setPending(undefined); dirty.current = false; accept(next);
            setNotice(operation.path.endsWith('/answer') && operation.body.commit === false ? 'Draft saved.' : 'Exam state saved.');
        } catch (e) {
            const failure = e as Error & { status?: number };
            setError(failure.message || 'Connection lost. Retry the pending request when you reconnect.');
            // Validation failures are definitive. Network/server failures may have committed.
            if (failure.status && failure.status >= 400 && failure.status < 500 && failure.status !== 429) {
                const saved = session(); persist({ token: saved.token, payload: saved.payload }); setPending(undefined);
                dirty.current = false;
                setTimeout(refresh, 0);
            }
        } finally { busyRef.current = false; setBusy(false); }
    }, [accept, refresh]);

    const mutate = async (path: string, body: Record<string, unknown> = {}) => {
        if (busyRef.current || pending || otherTab || !payload) return;
        const saved = session();
        if (saved.pending || saved.payload.attempt.id !== payload.attempt.id || saved.payload.state_version !== payload.state_version) {
            setOtherTab(true); return;
        }
        const operation: Pending = { path: '/api/candidate/' + path, token: saved.token, attemptId: payload.attempt.id,
            body: { ...body, device_fingerprint: fingerprint() } };
        persist({ ...saved, pending: operation }); setPending(operation);
        await send(operation);
    };

    const event = useCallback(async (event_type: string, metadata: Record<string, unknown> = {}) => {
        try {
            const saved = session();
            const result = await request('/api/candidate/event', saved.token, { event_type, metadata });
            if (result.disqualified) await refresh();
        } catch { /* A later heartbeat/reconnect records the restored connection. */ }
    }, [refresh]);

    useEffect(() => {
        try { setPending(session().pending); } catch { navigate('/exam/login', { replace: true }); return; }
        void refresh();
        const poll = window.setInterval(() => { if (!dirty.current && !session().pending) void refresh(); }, 10000);
        const online = () => { void event('network_reconnect'); void refresh(); };
        const offline = () => setError('You are offline. Reconnect and retry any pending request.');
        const storage = (e: StorageEvent) => {
            if (e.key !== sessionKey) return;
            if (!e.newValue) { navigate('/exam/login', { replace: true }); return; }
            try {
                const saved = session();
                if (saved.pending || saved.payload.attempt.id !== payloadRef.current?.attempt.id || saved.payload.state_version !== payloadRef.current?.state_version) {
                    setOtherTab(true); setPending(saved.pending);
                }
            } catch { navigate('/exam/login', { replace: true }); }
        };
        window.addEventListener('online', online); window.addEventListener('offline', offline); window.addEventListener('storage', storage);
        return () => { clearInterval(poll); window.removeEventListener('online', online); window.removeEventListener('offline', offline); window.removeEventListener('storage', storage); };
    }, [event, navigate, refresh]);

    useEffect(() => () => { stream.current?.getTracks().forEach(track => track.stop()); }, []);

    useEffect(() => {
        if (payload?.submitted || payload?.attempt.status === 'disqualified') {
            stream.current?.getTracks().forEach(track => track.stop());
            stream.current = null;
        }
    }, [payload?.submitted, payload?.attempt.status]);

    useEffect(() => {
        const interval = window.setInterval(() => {
            const next = Math.max(0, Math.ceil((deadline.current - Date.now()) / 1000)); setRemaining(next);
            if (payloadRef.current?.attempt.status === 'in_progress' && next === 0 && !busyRef.current && !expiryRequested.current) {
                expiryRequested.current = true; dirty.current = false; void refresh();
            }
        }, 1000);
        return () => clearInterval(interval);
    }, [refresh]);

    useEffect(() => {
        if (payload?.attempt.status !== 'in_progress') return;
        const blur = () => { void event('window_blur'); };
        const focus = () => { void event('focus_restored'); void refresh(); };
        const fullscreen = () => {
            if (!document.fullscreenElement && payload.exam.settings.require_fullscreen) {
                setControlsReady(false); void event('fullscreen_exit');
            }
        };
        const copy = (e: Event) => { e.preventDefault(); void event(e.type === 'contextmenu' ? 'right_click' : e.type === 'cut' ? 'copy_attempt' : e.type + '_attempt'); };
        const keyboard = (e: KeyboardEvent) => { if ((e.ctrlKey || e.metaKey) && ['p','s'].includes(e.key.toLowerCase())) { e.preventDefault(); void event('copy_attempt'); } };
        const heartbeat = window.setInterval(() => {
            const metadata: Record<string, unknown> = { severity: 'info', webcam_active: Boolean(stream.current?.active) };
            if (payload.exam.settings.monitor_screenshots && video.current?.readyState === 4) {
                const canvas = document.createElement('canvas'); canvas.width = 320; canvas.height = 240;
                canvas.getContext('2d')?.drawImage(video.current, 0, 0, 320, 240);
                metadata.webcam_snapshot = canvas.toDataURL('image/jpeg', 0.6);
            }
            void event(payload.exam.settings.require_webcam ? 'webcam_heartbeat' : 'heartbeat', metadata);
        }, 30000);
        window.addEventListener('blur', blur); window.addEventListener('focus', focus);
        document.addEventListener('fullscreenchange', fullscreen); document.addEventListener('keydown', keyboard);
        ['copy','paste','cut','contextmenu'].forEach(name => document.addEventListener(name, copy));
        return () => {
            clearInterval(heartbeat); window.removeEventListener('blur', blur); window.removeEventListener('focus', focus);
            document.removeEventListener('fullscreenchange', fullscreen); document.removeEventListener('keydown', keyboard);
            ['copy','paste','cut','contextmenu'].forEach(name => document.removeEventListener(name, copy));
        };
    }, [event, payload?.attempt.status, payload?.attempt.id, payload?.exam.settings.require_fullscreen, payload?.exam.settings.require_webcam, payload?.exam.settings.monitor_screenshots, refresh]);

    const prepareControls = async () => {
        setError('');
        try {
            if (payload?.exam.settings.require_webcam && !stream.current?.active) {
                if (!navigator.mediaDevices?.getUserMedia) throw new Error('A webcam is required. Use a supported browser and allow camera access.');
                stream.current = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                stream.current.getVideoTracks().forEach(track => track.addEventListener('ended', () => { setControlsReady(false); void event('webcam_disconnected'); }));
                if (video.current) video.current.srcObject = stream.current;
            }
            if (payload?.exam.settings.require_fullscreen && !document.fullscreenElement) await document.documentElement.requestFullscreen();
            setControlsReady(true);
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Allow the required exam controls to continue.');
            void event(payload?.exam.settings.require_webcam ? 'webcam_permission_failed' : 'fullscreen_permission_failed', { severity: 'high' });
        }
    };
    const answer = (commit: boolean) => {
        if (!payload?.current_item || !controlsReady || remaining <= 0) return;
        if (commit && selected.length === 0 && !window.confirm('Continue without answering? This question cannot be revisited.')) return;
        void mutate('answer', { question_id: payload.current_item.question_id, selected_option_ids: selected, state_version: payload.state_version,
            commit, ...(commit ? { idempotency_key: operationId() } : {}) });
    };
    const nextLevel = async (practice: boolean) => {
        try {
            await mutate('next-level', { practice, idempotency_key: operationId() });
        } catch (e) {
            setError(e instanceof Error ? e.message : 'Unable to start the next level. Refresh the exam state and try again.');
        }
    };
    const disabled = busy || Boolean(pending) || otherTab;
    return <main className="min-h-screen bg-slate-50 px-4 py-8 text-slate-900">
        <div className="mx-auto max-w-3xl space-y-5">
            <header className="rounded-lg border bg-white p-5">
                <p className="text-sm font-semibold text-primary">AlignEx · Adaptive examination</p>
                <h1 className="mt-1 text-2xl font-bold">{payload?.exam.title || 'Restoring your exam'}</h1>
                {payload && <p className="mt-2 text-sm">{payload.candidate.full_name} · {payload.candidate.registration_number} · Level {payload.level}{payload.is_practice ? ' · Unscored practice' : ''}</p>}
            </header>
            {error && <div ref={errorAlert} tabIndex={-1} role="alert" className="rounded-md border border-red-300 bg-red-50 p-4">{error}</div>}
            {notice && <p role="status" className="text-primary">{notice}</p>}
            {otherTab && <div role="alert" className="rounded-md border border-amber-300 bg-amber-50 p-4">This exam changed in another tab. Refresh its server state before continuing.</div>}
            <div className="flex flex-wrap gap-3">
                <Button variant="secondary" disabled={busy} onClick={() => { dirty.current = false; void refresh(); }}>Refresh exam state</Button>
                {pending && <Button disabled={busy} onClick={() => void send(pending)}>Retry pending request</Button>}
            </div>
            {!payload && <p role="status">Loading your server state…</p>}
            {payload && <>
                {payload.exam.settings.require_webcam && <video ref={node => { video.current = node; if (node && stream.current) node.srcObject = stream.current; }} autoPlay muted playsInline aria-label="Your webcam preview" className="h-24 w-32 rounded border bg-slate-900" />}
                {payload.attempt.status === 'disqualified' ? <section className="rounded-lg border bg-white p-6"><h2 className="text-xl font-bold">Exam disqualified</h2><p>Contact your supervisor. Further answers and recovery levels are unavailable.</p></section> :
                payload.submitted ? <section className="space-y-4 rounded-lg border bg-white p-6">
                    <h2 className="text-xl font-bold">Level completed</h2>
                    <p>{payload.stop_reason === 'timeout' ? 'Time expired. Your committed responses were finalized.'
                        : payload.stop_reason === 'supervisor_end' ? 'Your supervisor ended this level. Your committed responses were finalized.'
                        : payload.stop_reason === 'pool_exhausted' ? 'No eligible questions remain. Your committed responses were finalized; contact your supervisor.'
                        : 'Your committed responses have been finalized.'}</p>
                    {payload.learning_progress && <div className="space-y-4">
                        <h3 className="text-lg font-semibold">Your progress</h3>
                        <p>You have earned {payload.learning_progress.earned_marks} of {payload.learning_progress.original_marks} marks so far. {payload.learning_progress.remaining_marks} marks remain available to improve.</p>
                        <AdaptiveImprovementChart progress={payload.learning_progress} />
                        <table className="w-full text-left"><thead><tr><th>Level</th><th>Marks earned</th><th>Available marks</th><th>Deduction for this level</th></tr></thead><tbody>
                            {payload.learning_progress.levels.map(row=><tr key={row.number}><td>{row.number}</td><td>{row.score}</td><td>{row.available_marks}</td><td>{row.penalty_marks}</td></tr>)}
                        </tbody></table>
                        <h3 className="text-lg font-semibold">Strengths and areas to improve</h3>
                        <ul>{payload.learning_progress.areas.map((area,index)=><li key={index}>{area.name}: {area.status}</li>)}</ul>
                        {payload.recovery.can_start && <p>Your next level uses new questions from the areas you need to improve. Your earned marks are kept.</p>}
                    </div>}
                    {payload.result ? <p className="text-lg font-semibold">Released aggregate result: {payload.result.score} / {payload.result.total_marks}</p> : <p>{payload.learning_progress ? 'Your final result will be available when the organizer releases it.' : 'Scores remain hidden until the aggregate result is ready and released.'}</p>}
                    <p>{payload.recovery.message}</p>
                    {payload.recovery.available_at && <p className="text-sm">Recovery available from {new Date(payload.recovery.available_at).toLocaleString()}.</p>}
                    {payload.recovery.can_start && <ConfirmDialog
                        trigger={<Button disabled={disabled}>{busy ? 'Starting next level…' : 'Start next level'}</Button>}
                        title="Start your next level?"
                        description={`This level focuses on areas to improve. It deducts ${payload.recovery.penalty_percent}% from the remaining available marks. Marks already earned are kept. The timer starts when you continue.`}
                        confirmLabel="Begin next level"
                        onConfirm={() => nextLevel(false)}
                    />}
                    {payload.recovery.can_practice && <ConfirmDialog
                        trigger={<Button disabled={disabled}>Start unscored practice</Button>}
                        title="Start unscored practice?"
                        description="This practice level helps you work on weaker areas. It cannot increase your score. The timer starts when you continue."
                        confirmLabel="Begin practice"
                        onConfirm={() => nextLevel(true)}
                    />}
                </section> : payload.attempt.status === 'not_started' ? <section className="space-y-4 rounded-lg border bg-white p-6">
                    <h2 className="text-xl font-bold">Before you begin</h2><p>{payload.exam.instructions}</p><p>The server finishes each level according to its area coverage and question limits. The questions you receive may differ from another candidate's.</p>
                    <p>Drafts can be changed until you confirm. Reconnecting or refreshing does not begin another level. Closing the browser does not stop the server timer.</p>
                    {!payload.can_start && <p role="status">This exam is not open for starting yet. Refresh its state when the scheduled window opens.</p>}
                    {!controlsReady && <Button disabled={disabled} onClick={() => void prepareControls()}>Enable required exam controls</Button>}
                    <Button disabled={disabled || !controlsReady || !payload.can_start} onClick={() => void mutate('start')}>{busy ? 'Starting…' : 'Start adaptive exam'}</Button>
                </section> : <section className="space-y-5 rounded-lg border bg-white p-6">
                    <div className="flex justify-between gap-3"><p>{payload.committed_questions} of {payload.total_questions} responses confirmed</p><p aria-label="Time remaining" className="font-mono font-bold">{clock(remaining)}</p></div>
                    {!controlsReady && <div role="alert"><p>Restore the required exam controls to continue.</p><Button onClick={() => void prepareControls()}>Restore exam controls</Button></div>}
                    {payload.current_item ? <>
                        <h2 ref={heading} tabIndex={-1} className="text-xl font-semibold">{payload.current_item.question_text}</h2>
                        <fieldset disabled={disabled || !controlsReady || remaining <= 0} className="space-y-3">
                            <legend className="mb-3 text-sm">{payload.current_item.question_type === 'multiple_choice' ? 'Select all applicable options.' : 'Select one option.'}</legend>
                            {payload.current_item.options.map(option => <label key={option.id} className="flex cursor-pointer gap-3 rounded-md border p-4 focus-within:ring-2 focus-within:ring-primary">
                                <input type={payload.current_item!.question_type === 'multiple_choice' ? 'checkbox' : 'radio'} name="adaptive-answer" value={option.id} checked={selected.includes(option.id)} onChange={e => {
                                    dirty.current = true;
                                    setSelected(payload.current_item!.question_type === 'multiple_choice' ? e.target.checked ? [...selected, option.id] : selected.filter(id => id !== option.id) : [option.id]);
                                }} />
                                <span>{option.label}. {option.option_text}</span>
                            </label>)}
                        </fieldset>
                        <p className="text-sm text-slate-600">Confirmation is final. You cannot return to a confirmed question.</p>
                        <div className="flex flex-wrap gap-3">
                            <Button variant="secondary" disabled={disabled || !controlsReady || remaining <= 0} onClick={() => answer(false)}>Save draft</Button>
                            <Button disabled={disabled || !controlsReady || remaining <= 0} onClick={() => answer(true)}>{busy ? 'Saving…' : payload.is_last_question ? 'Finish level' : 'Confirm and continue'}</Button>
                        </div>
                    </> : <p role="status">No current question is available. Refresh the server state or contact your supervisor.</p>}
                </section>}
            </>}
            <Button variant="ghost" disabled={busy || Boolean(pending)} onClick={() => {
                if (payload?.attempt.status === 'in_progress' && !window.confirm('Leave the exam? The server timer will continue.')) return;
                clearAdaptiveSession(); localStorage.removeItem('alignex_exam_token'); localStorage.removeItem('alignex_exam_payload'); navigate('/exam/login');
            }}>Return to login</Button>
        </div>
    </main>;
}
