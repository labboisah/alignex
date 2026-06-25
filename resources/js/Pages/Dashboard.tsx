import { Head } from '@inertiajs/react';
import { BarChart3, CheckCircle2, Code2, Route } from 'lucide-react';
import { Bar, BarChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { PageHeader, PortalAppShell } from '@/Components/Platform';
import { Button } from '../Components/ui/button';

const checks = [
    { label: 'Inertia React page rendering', icon: CheckCircle2 },
    { label: 'React + TypeScript via Vite', icon: Code2 },
    { label: 'Candidate exam router reserved for later', icon: Route },
];

const sampleData = [
    { name: 'React', value: 1 },
    { name: 'Tailwind', value: 1 },
    { name: 'Recharts', value: 1 },
];

export default function Dashboard() {
    return (
        <PortalAppShell title="Dashboard">
            <Head title="Dashboard" />
            <section className="mx-auto max-w-5xl">
                <PageHeader
                    eyebrow="AlignEx setup"
                    title="Authenticated portal is ready."
                    description="This temporary dashboard verifies Laravel session authentication, Inertia React, TypeScript, Tailwind, shadcn-style UI primitives, lucide-react, and Recharts."
                />

                <div className="grid gap-4 md:grid-cols-3">
                    {checks.map(({ label, icon: Icon }) => (
                        <div key={label} className="rounded-md border border-border bg-white p-5 shadow-sm">
                            <Icon className="h-5 w-5 text-primary" />
                            <p className="mt-4 text-sm font-semibold">{label}</p>
                        </div>
                    ))}
                </div>

                <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
                    <div className="rounded-md border border-border bg-white p-5 shadow-sm">
                        <div className="mb-4 flex items-center gap-2">
                            <BarChart3 className="h-5 w-5 text-accent" />
                            <h2 className="font-semibold">Chart Smoke Test</h2>
                        </div>
                        <div className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={sampleData}>
                                    <XAxis dataKey="name" />
                                    <YAxis hide />
                                    <Tooltip />
                                    <Bar dataKey="value" fill="#0F7A3A" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </div>

                    <div className="rounded-md border border-border bg-white p-5 shadow-sm">
                        <h2 className="font-semibold">shadcn/ui Smoke Test</h2>
                        <p className="mt-2 text-sm leading-6 text-slate-600">
                            The button below uses a local shadcn-style primitive backed by Radix Slot and Tailwind utility merging.
                        </p>
                        <Button className="mt-5" type="button">Ready for Step 2</Button>
                    </div>
                </div>
            </section>
        </PortalAppShell>
    );
}
