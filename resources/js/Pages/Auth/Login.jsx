import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { AppLogo } from '@/Components/Platform';
import { Button } from '@/Components/ui/button';
import { Head, Link, useForm } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <main className="grid min-h-screen bg-surface text-slateDark lg:grid-cols-[0.95fr_1.05fr]">
            <Head title="Log in" />

            <section className="hidden border-r border-border bg-white px-10 py-12 lg:flex lg:flex-col lg:justify-between">
                <AppLogo />
                <div className="max-w-xl">
                    <div className="mb-5 inline-flex items-center gap-2 rounded-md bg-green-50 px-3 py-1 text-sm font-semibold text-primary">
                        <LockKeyhole className="h-4 w-4" />
                        Admin portal
                    </div>
                    <h1 className="text-4xl font-bold leading-tight text-primaryDark">
                        Secure access for examination teams.
                    </h1>
                    <p className="mt-4 text-sm leading-7 text-slate-600">
                        Use this portal for administrators, exam managers, authors, reviewers, supervisors, and support users. Candidate exam access remains separate at /exam/login.
                    </p>
                </div>
                <div className="text-sm text-slate-500">AlignEx authentication is powered by Laravel sessions and Inertia.</div>
            </section>

            <section className="flex items-center justify-center px-6 py-12">
                <div className="w-full max-w-md rounded-md border border-border bg-white p-6 shadow-sm">
                    <div className="mb-6 lg:hidden">
                        <AppLogo />
                    </div>
                    <h2 className="text-2xl font-bold text-primaryDark">Sign in</h2>
                    <p className="mt-2 text-sm text-slate-600">Access the AlignEx admin portal.</p>

                    {status && (
                        <div className="mt-4 rounded-md bg-green-50 p-3 text-sm font-medium text-success">
                            {status}
                        </div>
                    )}

                    <form onSubmit={submit} className="mt-6">
                        <div>
                            <InputLabel htmlFor="email" value="Email" />

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

                        <div className="mt-4">
                            <InputLabel htmlFor="password" value="Password" />

                            <TextInput
                                id="password"
                                type="password"
                                name="password"
                                value={data.password}
                                className="mt-1 block w-full"
                                autoComplete="current-password"
                                onChange={(e) => setData('password', e.target.value)}
                            />

                            <InputError message={errors.password} className="mt-2" />
                        </div>

                        <div className="mt-4 block">
                            <label className="flex items-center">
                                <Checkbox
                                    name="remember"
                                    checked={data.remember}
                                    onChange={(e) =>
                                        setData('remember', e.target.checked)
                                    }
                                />
                                <span className="ms-2 text-sm text-slate-600">
                                    Remember me
                                </span>
                            </label>
                        </div>

                        <div className="mt-6 flex items-center justify-between gap-4">
                            {canResetPassword && (
                                <Link
                                    href={route('password.request')}
                                    className="rounded-md text-sm font-semibold text-primary hover:text-primaryDark"
                                >
                                    Forgot password?
                                </Link>
                            )}

                            <Button type="submit" disabled={processing}>
                                {processing ? 'Signing in...' : 'Log in'}
                            </Button>
                        </div>
                    </form>

                    <div className="mt-6 rounded-md bg-slate-50 p-3 text-sm text-slate-600">
                        Need to register an organization, school, or CBT center?{' '}
                        <Link href="/register-admin" className="font-semibold text-primary hover:text-primaryDark">
                            Submit an admin registration request.
                        </Link>
                    </div>
                </div>
            </section>
        </main>
    );
}
