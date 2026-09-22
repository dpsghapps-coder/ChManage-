import { Form, Head } from '@inertiajs/react';
import ForcePasswordChangeController from '@/actions/App/Http/Controllers/Auth/ForcePasswordChangeController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export default function ForcePasswordChange({
    passwordRules,
}: {
    passwordRules: string;
}) {
    return (
        <>
            <Head title="Choose a New Password" />

            <Form
                {...ForcePasswordChangeController.update.form()}
                resetOnSuccess={[
                    'current_password',
                    'password',
                    'password_confirmation',
                ]}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="current_password">
                                Temporary password
                            </Label>
                            <PasswordInput
                                id="current_password"
                                name="current_password"
                                required
                                autoFocus
                                autoComplete="current-password"
                                placeholder="The password you signed in with"
                            />
                            <InputError message={errors.current_password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">New password</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                                placeholder="New password"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirm new password
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                                placeholder="Confirm new password"
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                            data-test="change-password-button"
                        >
                            {processing && <Spinner />}
                            Save New Password
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

ForcePasswordChange.layout = {
    title: 'Choose a New Password',
    description:
        'You signed in with a temporary password. Set your own before you continue.',
};
