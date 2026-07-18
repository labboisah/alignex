import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { AppLogo } from '@/Components/Platform';
import TextInput from '@/Components/TextInput';
import { Button } from '@/Components/ui/button';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, KeyRound } from 'lucide-react';

export default function ResetPassword({ token, email }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <main className="flex min-h-screen items-center justify-center bg-surface px-6 py-12 text-slateDark">
            <Head title="Reset Password" />

            <div className="w-full max-w-md rounded-md border border-border bg-white p-6 shadow-sm">
                <AppLogo />

                <div className="mt-6 flex items-start gap-3">
                    <div className="rounded-md bg-green-50 p-2 text-primary">
                        <KeyRound className="h-5 w-5" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold text-primaryDark">Create new password</h1>
                        <p className="mt-2 text-sm leading-6 text-slate-600">
                            Choose a new password for your AlignEx portal account.
                        </p>
                    </div>
                </div>

                <form onSubmit={submit} className="mt-6 space-y-5">
                    <div>
                        <InputLabel htmlFor="email" value="Email" />

                        <TextInput
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            className="mt-1 block w-full"
                            autoComplete="username"
                            onChange={(e) => setData('email', e.target.value)}
                        />

                        <InputError message={errors.email} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="password" value="Password" />

                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            className="mt-1 block w-full"
                            autoComplete="new-password"
                            isFocused={true}
                            onChange={(e) => setData('password', e.target.value)}
                        />

                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel
                            htmlFor="password_confirmation"
                            value="Confirm Password"
                        />

                        <TextInput
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            value={data.password_confirmation}
                            className="mt-1 block w-full"
                            autoComplete="new-password"
                            onChange={(e) =>
                                setData('password_confirmation', e.target.value)
                            }
                        />

                        <InputError
                            message={errors.password_confirmation}
                            className="mt-2"
                        />
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
                            {processing ? 'Resetting...' : 'Reset password'}
                        </Button>
                    </div>
                </form>
            </div>
        </main>
    );
}
