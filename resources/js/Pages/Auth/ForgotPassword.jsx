import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { AppLogo } from '@/Components/Platform';
import TextInput from '@/Components/TextInput';
import { Button } from '@/Components/ui/button';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, MailCheck } from 'lucide-react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <main className="flex min-h-screen items-center justify-center bg-surface px-6 py-12 text-slateDark">
            <Head title="Forgot Password" />

            <div className="w-full max-w-md rounded-md border border-border bg-white p-6 shadow-sm">
                <AppLogo />

                <div className="mt-6 flex items-start gap-3">
                    <div className="rounded-md bg-green-50 p-2 text-primary">
                        <MailCheck className="h-5 w-5" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-primaryDark">Reset password</h1>
                        <p className="mt-2 text-sm leading-6 text-slate-600">
                            Enter your portal email address and AlignEx will send a secure reset link.
                        </p>
                    </div>
                </div>

                {status && (
                    <div className="mt-5 rounded-md bg-green-50 p-3 text-sm font-medium text-success">
                        {status}
                    </div>
                )}

                <form onSubmit={submit} className="mt-6 space-y-5">
                    <div>
                        <InputLabel htmlFor="email" value="Email address" />

                        <TextInput
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            className="mt-1 block w-full"
                            autoComplete="username"
                            isFocused={true}
                            onChange={(e) => setData('email', e.target.value)}
                        />

                        <InputError message={errors.email} className="mt-2" />
                    </div>

                    <div className="flex items-center justify-between gap-4">
                        <Link
                            href={route('login')}
                            className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:text-primaryDark"
                        >
                            <ArrowLeft className="h-4 w-4" />
                            Back to login
                        </Link>

                        <Button type="submit" disabled={processing}>
                            {processing ? 'Sending...' : 'Send reset link'}
                        </Button>
                    </div>
                </form>
            </div>
        </main>
    );
}
