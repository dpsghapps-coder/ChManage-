import { Form, Head, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';

export default function Profile() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Profile Settings" />

            <h1 className="sr-only">Profile Settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Profile"
                    description="Update your name and email address"
                />

                <dl className="grid gap-3 rounded-lg border p-4 text-sm sm:grid-cols-3">
                    <div>
                        <dt className="text-muted-foreground">Username</dt>
                        <dd className="font-medium">{auth.user.username}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Role</dt>
                        <dd className="font-medium">
                            {auth.user.role?.name ?? 'None assigned'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Staff record</dt>
                        <dd className="font-medium">
                            {auth.user.staff
                                ? `${auth.user.staff.full_name} (${auth.user.staff.staff_number})`
                                : 'Not linked'}
                        </dd>
                    </div>
                </dl>

                <Form
                    {...ProfileController.update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="first_name">
                                        First name
                                    </Label>
                                    <Input
                                        id="first_name"
                                        name="first_name"
                                        defaultValue={
                                            auth.user.first_name ?? ''
                                        }
                                        required
                                        autoComplete="given-name"
                                    />
                                    <InputError message={errors.first_name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="last_name">Last name</Label>
                                    <Input
                                        id="last_name"
                                        name="last_name"
                                        defaultValue={auth.user.last_name ?? ''}
                                        autoComplete="family-name"
                                    />
                                    <InputError message={errors.last_name} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    Email address (optional)
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    defaultValue={auth.user.email ?? ''}
                                    autoComplete="email"
                                    placeholder="Used for password-reset links"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <Button
                                disabled={processing}
                                data-test="update-profile-button"
                            >
                                Save
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile Settings',
            href: edit(),
        },
    ],
};
