import { Head, Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { AppLogo } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';

export default function RegistrationThankYou() {
    return (
        <main className="flex min-h-screen items-center justify-center bg-surface px-4 text-slateDark">
            <Head title="Registration Submitted" />
            <div className="w-full max-w-xl rounded-md border border-border bg-white p-6 text-center shadow-sm">
                <div className="mb-5 flex justify-center">
                    <AppLogo />
                </div>
                <CheckCircle2 className="mx-auto h-12 w-12 text-success" />
                <h1 className="mt-4 text-2xl font-bold text-primaryDark">Registration submitted</h1>
                <p className="mt-3 text-sm leading-6 text-slate-600">
                    Your request is pending super admin review. Once approved, you can log in with the admin email and password you submitted.
                </p>
                <div className="mt-6 flex justify-center gap-2">
                    <Button asChild type="button" variant="secondary">
                        <Link href="/">Home</Link>
                    </Button>
                    <Button asChild type="button">
                        <Link href="/login">Login</Link>
                    </Button>
                </div>
            </div>
        </main>
    );
}
