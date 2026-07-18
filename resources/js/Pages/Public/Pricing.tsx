import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, ShieldCheck } from 'lucide-react';
import { Button } from '@/Components/ui/button';

type PricingPlan = {
    id: number;
    slug: string;
    name: string;
    formatted_price: string;
    billing_label: string;
    description: string;
    highlights: string[];
    feature_items: { key: string; label: string; enabled: boolean }[];
    cta_label: string;
    is_featured: boolean;
};

export default function Pricing({ plans }: { plans: { data: PricingPlan[] } }) {
    const pricingPlans = plans.data;

    return (
        <>
            <Head title="Pricing" />
            <main className="min-h-screen bg-surface text-slateDark">
                <header className="border-b border-border bg-white">
                    <nav className="mx-auto flex h-16 max-w-7xl items-center justify-between px-6 lg:px-8">
                        <Link href="/" className="flex items-center gap-3">
                            <img src="/images/brand-logo.png" alt="AlignEx" className="h-12 w-auto max-w-[190px] object-contain" />
                        </Link>
                        <div className="flex items-center gap-2">
                            <Button asChild variant="ghost" className="hidden sm:inline-flex">
                                <Link href="/">Home</Link>
                            </Button>
                            <Button asChild variant="ghost" className="hidden sm:inline-flex">
                                <Link href="/login">Login</Link>
                            </Button>
                            <Button asChild>
                                <Link href="/register-admin">Register</Link>
                            </Button>
                        </div>
                    </nav>
                </header>

                <section className="border-b border-border bg-white">
                    <div className="mx-auto max-w-7xl px-6 py-14 lg:px-8">
                        <div className="max-w-3xl">
                            <div className="mb-5 inline-flex items-center gap-2 rounded-md border border-green-200 bg-green-50 px-3 py-1 text-sm font-semibold text-primary">
                                <ShieldCheck className="h-4 w-4" />
                                CBT Pricing
                            </div>
                            <h1 className="text-4xl font-bold leading-tight text-primaryDark sm:text-5xl">
                                Simple yearly plans for online and offline exam delivery
                            </h1>
                            <p className="mt-5 text-base leading-8 text-slate-600 sm:text-lg">
                                Choose the AlignEx plan that matches your candidates, reporting needs, delivery mode, devices, and support level. Free Plan can be used forever for demo and light practice use.
                            </p>
                        </div>
                    </div>
                </section>

                <section className="mx-auto max-w-7xl px-6 py-16 lg:px-8">
                    <div className="rounded-md border border-green-200 bg-green-50 p-4 text-sm leading-7 text-primaryDark">
                        Plans can support online portal exams, offline center exams, or a combined delivery model. Offline delivery includes portal activation, secure exam package import, local candidate delivery, answer capture, and result package export. Free Plan is available forever, but it remains marked for demo or practice use and is not allowed for official live exams.
                    </div>

                    <div className="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-5">
                        {pricingPlans.map((plan) => (
                            <div
                                key={plan.name}
                                className={`flex flex-col rounded-md border bg-white p-5 shadow-sm ${plan.is_featured ? 'border-primary ring-2 ring-green-100' : 'border-border'}`}
                            >
                                {plan.is_featured && (
                                    <div className="mb-4 w-fit rounded-md bg-primary px-2.5 py-1 text-xs font-bold uppercase text-white">
                                        Recommended
                                    </div>
                                )}
                                <h2 className="text-lg font-bold text-primaryDark">{plan.name}</h2>
                                <div className="mt-4">
                                    <span className="text-2xl font-bold text-slateDark">{plan.formatted_price}</span>
                                    <span className="mt-1 block text-xs font-semibold uppercase text-slate-500">{plan.billing_label}</span>
                                </div>
                                <p className="mt-4 min-h-[84px] text-sm leading-7 text-slate-600">{plan.description}</p>
                                <div className="mt-5 space-y-3">
                                    {plan.highlights.map((highlight) => (
                                        <div key={highlight} className="flex gap-2 text-sm font-medium text-slate-700">
                                            <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-success" />
                                            <span>{highlight}</span>
                                        </div>
                                    ))}
                                </div>
                                <div className="mt-5 border-t border-border pt-4">
                                    <div className="text-xs font-bold uppercase text-slate-500">Included Features</div>
                                    <div className="mt-3 space-y-2">
                                        {plan.feature_items
                                            .filter((feature) => feature.enabled && !['official_live_exam_allowed', 'demo_watermark'].includes(feature.key))
                                            .slice(0, 8)
                                            .map((feature) => (
                                                <div key={feature.key} className="flex gap-2 text-xs font-medium text-slate-600">
                                                    <CheckCircle2 className="mt-0.5 h-3.5 w-3.5 shrink-0 text-success" />
                                                    <span>{feature.label}</span>
                                                </div>
                                            ))}
                                    </div>
                                </div>
                                <Button asChild variant={plan.is_featured ? 'default' : 'secondary'} className="mt-6 w-full">
                                    <Link href={`/register-admin?plan=${plan.slug}`}>{plan.cta_label}</Link>
                                </Button>
                            </div>
                        ))}
                    </div>
                </section>
            </main>
        </>
    );
}
