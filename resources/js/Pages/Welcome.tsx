import { Head, Link } from '@inertiajs/react';
import { Button } from '../Components/ui/button';

export default function Welcome() {
    return (
        <>
            <Head title="Welcome" />
            <main className="flex min-h-screen items-center justify-center bg-surface px-6 text-slateDark">
                <div className="max-w-xl text-center">
                    <p className="text-sm font-semibold uppercase text-primary">AlignEx</p>
                    <h1 className="mt-3 text-4xl font-bold">Laravel + Inertia React is configured.</h1>
                    <p className="mt-4 text-slate-600">This is a temporary setup screen for the base stack.</p>
                    <div className="mt-6 flex flex-wrap justify-center gap-3">
                        <Button asChild>
                            <Link href="/dashboard">Open Dashboard</Link>
                        </Button>
                        <Button asChild variant="secondary">
                            <Link href="/candidate-result">Check Exam Result</Link>
                        </Button>
                    </div>
                </div>
            </main>
        </>
    );
}
