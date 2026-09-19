import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, CheckCircle2, ClipboardCheck, Gauge, Laptop, Network, ShieldCheck, UploadCloud } from 'lucide-react';
import { ReactNode } from 'react';
import { Button } from '@/Components/ui/button';

type GuideStep = { title: string; body: string };

const setupSteps: GuideStep[] = [
    { title: 'Activate the Center Server', body: 'Install the AlignEx Center Server, activate it with the approved center account, and confirm the center name, device identity, license status, and portal URL.' },
    { title: 'Connect client computers', body: 'Install the approved Candidate Client or open the LAN client URL on every test computer. Confirm that each device can reach the server and that the network is stable.' },
    { title: 'Create a readiness drill', body: 'From Center Server, open Autoboot Readiness and create a synthetic drill. Set the expected client count, duration, and drill name.' },
    { title: 'Confirm the client snapshot', body: 'Wait for the expected number of readiness clients to register. Do not start until the connected count matches the center plan.' },
    { title: 'Start autoboot', body: 'Start the drill from the server. Readiness clients receive the test manifest, show a readiness-test screen, answer automatically, and submit synthetic activity.' },
    { title: 'Finalize and upload the report', body: 'After the clients complete, finalize the local report, review the metrics and checksum, then upload the report to the AlignEx portal.' },
];

const checks: GuideStep[] = [
    { title: 'Connection coverage', body: 'Expected clients compared with clients connected at the locked start snapshot.' },
    { title: 'Completion rate', body: 'Clients that completed the synthetic question sequence compared with connected clients.' },
    { title: 'Answer throughput', body: 'Total synthetic answers acknowledged by the server during the drill.' },
    { title: 'Submission coverage', body: 'Synthetic clients that submitted completion events successfully.' },
    { title: 'Event and error evidence', body: 'Heartbeat, registration, answer, reconnect, failure, and submission events available for investigation.' },
    { title: 'Report integrity', body: 'The report payload receives a SHA-256 checksum before portal upload.' },
];

export default function AutobootReadiness() {
    return (
        <>
            <Head title="Autoboot Readiness Guide" />
            <main className="min-h-screen bg-surface text-slateDark">
                <PublicNav />
                <Hero />
                <section className="mx-auto max-w-7xl px-6 py-12 lg:px-8">
                    <SectionHeading eyebrow="Before the exam" title="Prepare the center in a controlled sequence" body="Autoboot is a synthetic readiness drill. It validates the center's server, LAN, client devices, local storage, and reporting path before real candidates are admitted." />
                    <div className="mt-7 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {setupSteps.map((step, index) => <StepCard key={step.title} index={index + 1} step={step} />)}
                    </div>
                </section>

                <section className="border-y border-border bg-white">
                    <div className="mx-auto max-w-7xl px-6 py-12 lg:px-8">
                        <SectionHeading eyebrow="Configuration" title="What administrators should configure" body="Use the readiness drill to model the real center capacity, not an arbitrary small test. The expected client count should match the computers that will be used on exam day." />
                        <div className="mt-7 grid gap-5 lg:grid-cols-3">
                            <InfoCard icon={<Network className="h-5 w-5" />} title="Expected clients" body="Set the number of client computers that must be connected before the drill may start. Late or missing devices should be investigated rather than silently ignored." />
                            <InfoCard icon={<Gauge className="h-5 w-5" />} title="Duration" body="Use a duration long enough to observe normal answer traffic, reconnect behavior, and local database writes. The server controls the authoritative window." />
                            <InfoCard icon={<ClipboardCheck className="h-5 w-5" />} title="Drill mode" body="Start with Synthetic Full Run. Later, controlled resilience and capacity modes can introduce planned reconnects or partial failures." />
                        </div>
                    </div>
                </section>

                <section className="mx-auto max-w-7xl px-6 py-12 lg:px-8">
                    <div className="grid gap-8 lg:grid-cols-[1fr_0.8fr]">
                        <div>
                            <SectionHeading eyebrow="During autoboot" title="What happens on the client computers" body="Clients enter an unmistakable Readiness Test screen. They register with the server, wait for the administrator's start command, receive the synthetic manifest, show progress, submit automatic answers, and report completion." />
                            <div className="mt-6 space-y-3">
                                {['Client registers with readiness mode, not candidate exam mode.', 'Server issues a short-lived readiness session for the client.', 'Client receives four synthetic subjects with 25 questions each.', 'Client displays timer and question progress while automatic activity runs.', 'Client emits answer and completion telemetry to the readiness-only endpoints.', 'Client never calls the official candidate login, answer, or result APIs.'].map((item) => <div key={item} className="flex gap-3 rounded-md border border-border bg-white p-4 shadow-sm"><CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-success" /><span className="text-sm leading-6 text-slate-600">{item}</span></div>)}
                            </div>
                        </div>
                        <div className="rounded-md border border-amber-200 bg-amber-50 p-6">
                            <AlertTriangle className="h-6 w-6 text-amber-700" />
                            <h2 className="mt-4 text-lg font-semibold text-amber-950">Important safety boundary</h2>
                            <p className="mt-3 text-sm leading-7 text-amber-900">Autoboot must never be started against a live candidate exam. It uses synthetic readiness data and stores reports separately from official candidate attempts and results.</p>
                        </div>
                    </div>
                </section>

                <section className="border-y border-border bg-white">
                    <div className="mx-auto max-w-7xl px-6 py-12 lg:px-8">
                        <SectionHeading eyebrow="Performance report" title="How to read the center report" body="The report is operational evidence. It answers whether the center is ready to conduct the real exam; it is not a candidate score report." />
                        <div className="mt-7 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {checks.map((check) => <InfoCard key={check.title} icon={<CheckCircle2 className="h-5 w-5" />} title={check.title} body={check.body} />)}
                        </div>
                        <div className="mt-7 rounded-md border border-green-200 bg-green-50 p-5 text-sm leading-7 text-green-950">
                            <strong>Recommended decision:</strong> mark the center ready only after the expected clients connect, the drill completes without unresolved client failures, the answer and submission counts are consistent, and the report uploads successfully to the portal.
                        </div>
                    </div>
                </section>

                <section className="mx-auto max-w-7xl px-6 py-12 lg:px-8">
                    <SectionHeading eyebrow="Troubleshooting" title="When a center should not be cleared" body="Do not approve a center only because the server starts. Investigate the operational evidence when any of these conditions occurs." />
                    <div className="mt-7 grid gap-4 lg:grid-cols-2">
                        {[
                            ['Missing clients', 'The connected snapshot is below the expected count or devices repeatedly fail to register.'],
                            ['Unresolved completion', 'One or more clients do not submit, remain disconnected, or report a client error.'],
                            ['Storage or server errors', 'SQLite write errors, crashes, high latency, or insufficient disk space appear during the drill.'],
                            ['Report upload failure', 'The local report is complete but cannot be accepted by the portal. Preserve the report and retry; do not delete it.'],
                        ].map(([title, body]) => <div key={title} className="rounded-md border border-border bg-white p-5 shadow-sm"><h3 className="font-semibold text-slateDark">{title}</h3><p className="mt-2 text-sm leading-6 text-slate-600">{body}</p></div>)}
                    </div>
                </section>

                <Footer />
            </main>
        </>
    );
}

function PublicNav() {
    return <header className="sticky top-0 z-30 border-b border-border bg-white/95 backdrop-blur"><nav className="mx-auto flex h-16 max-w-7xl items-center justify-between px-6 lg:px-8"><Link href="/" className="flex items-center gap-3"><img src="/images/brand-logo.png" alt="AlignEx" className="h-12 w-auto max-w-[190px] object-contain" /></Link><div className="hidden items-center gap-6 text-sm font-semibold text-slate-600 md:flex"><Link href="/documentation" className="hover:text-primary">Documentation</Link><a href="#setup" className="hover:text-primary">Setup</a><a href="#report" className="hover:text-primary">Report</a></div><div className="flex items-center gap-2"><Button asChild variant="ghost" className="hidden sm:inline-flex"><Link href="/login">Login</Link></Button><Button asChild><Link href="/register-admin">Register</Link></Button></div></nav></header>;
}

function Hero() {
    return <section id="setup" className="border-b border-border bg-white"><div className="mx-auto grid max-w-7xl gap-8 px-6 py-14 lg:grid-cols-[1fr_0.8fr] lg:px-8"><div><div className="mb-5 inline-flex rounded-md border border-green-200 bg-green-50 px-3 py-1 text-sm font-semibold text-primary">Center operations guide</div><h1 className="max-w-4xl text-4xl font-bold leading-tight text-primaryDark lg:text-6xl">Autoboot Readiness For CBT Centers</h1><p className="mt-6 max-w-3xl text-base leading-8 text-slate-600">Prepare, configure, run, and report a synthetic readiness drill before your center hosts a real examination.</p><div className="mt-8 flex flex-wrap gap-3"><Button asChild><a href="#setup">Setup steps <ArrowRight className="h-4 w-4" /></a></Button><Button asChild variant="secondary"><a href="#report">Read the report guide</a></Button></div></div><div className="rounded-md border border-border bg-slate-50 p-6 shadow-sm"><div className="flex items-center gap-3"><Laptop className="h-6 w-6 text-primary" /><h2 className="font-semibold text-slateDark">Readiness drill outcome</h2></div><div className="mt-5 space-y-3">{['Clients connected', 'Synthetic questions processed', 'Automatic submissions completed', 'Report uploaded to portal'].map((item) => <div key={item} className="flex items-center gap-3 rounded-md border border-border bg-white p-3 text-sm font-semibold text-slate-700"><CheckCircle2 className="h-4 w-4 text-success" />{item}</div>)}</div></div></div></section>;
}

function SectionHeading({ eyebrow, title, body }: { eyebrow: string; title: string; body: string }) { return <div><div className="text-sm font-bold uppercase tracking-wide text-primary">{eyebrow}</div><h2 className="mt-2 text-2xl font-bold text-primaryDark lg:text-3xl">{title}</h2><p className="mt-3 max-w-4xl text-sm leading-7 text-slate-600">{body}</p></div>; }
function StepCard({ index, step }: { index: number; step: GuideStep }) { return <div className="rounded-md border border-border bg-slate-50 p-4"><div className="flex gap-3"><span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-white">{index}</span><div><h3 className="font-semibold text-slateDark">{step.title}</h3><p className="mt-1 text-sm leading-6 text-slate-600">{step.body}</p></div></div></div>; }
function InfoCard({ icon, title, body }: { icon: ReactNode; title: string; body: string }) { return <div className="rounded-md border border-border bg-white p-5 shadow-sm"><div className="w-fit rounded-md bg-green-50 p-2 text-primary">{icon}</div><h3 className="mt-4 font-semibold text-slateDark">{title}</h3><p className="mt-2 text-sm leading-6 text-slate-600">{body}</p></div>; }
function Footer() { return <footer id="report" className="border-t border-border bg-white"><div className="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-8 text-sm text-slate-500 lg:flex-row lg:items-center lg:justify-between lg:px-8"><div>AlignEx center readiness operations.</div><div className="flex flex-wrap gap-4 font-semibold"><Link href="/" className="hover:text-primary">Home</Link><Link href="/documentation" className="hover:text-primary">Documentation</Link><Link href="/login" className="hover:text-primary">Login</Link></div></div></footer>; }
