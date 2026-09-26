import { Head, useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import type { FormEvent } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/portal/login';

export default function PortalLogin() {
    const form = useForm({ phone: '', date_of_birth: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store().url);
    };

    return (
        <div className="flex min-h-svh items-center justify-center bg-background p-6">
            <Head title="Member portal" />

            <div className="w-full max-w-sm space-y-8">
                <div className="space-y-2 text-center">
                    <AppLogoIcon className="mx-auto size-10 fill-current text-foreground" />
                    <h1 className="text-xl font-medium">Member portal</h1>
                    <p className="text-sm text-muted-foreground">
                        Sign in with the phone number the church has for you and
                        your date of birth.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-2">
                        <Label htmlFor="phone">Phone number</Label>
                        <Input
                            id="phone"
                            type="tel"
                            inputMode="tel"
                            autoComplete="tel"
                            placeholder="0244123456"
                            value={form.data.phone}
                            onChange={(e) =>
                                form.setData('phone', e.target.value)
                            }
                            autoFocus
                            required
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="date_of_birth">Date of birth</Label>
                        <Input
                            id="date_of_birth"
                            type="date"
                            autoComplete="bday"
                            max={new Date().toISOString().slice(0, 10)}
                            value={form.data.date_of_birth}
                            onChange={(e) =>
                                form.setData('date_of_birth', e.target.value)
                            }
                            required
                        />
                        <InputError
                            message={
                                form.errors.phone ?? form.errors.date_of_birth
                            }
                        />
                    </div>
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={form.processing}
                    >
                        {form.processing && (
                            <Loader2 className="animate-spin" />
                        )}{' '}
                        Sign in
                    </Button>
                </form>

                <p className="text-center text-xs text-muted-foreground">
                    Not on the list, or your number has changed? Please contact
                    the church office.
                </p>
            </div>
        </div>
    );
}
