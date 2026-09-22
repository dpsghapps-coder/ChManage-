import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Check, Copy, KeyRound } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { useClipboard } from '@/hooks/use-clipboard';
import { usePermission } from '@/hooks/use-permission';
import { index, resetPassword, store, update } from '@/routes/admin/users';

type Props = {
    user: {
        id: number;
        username: string;
        name: string;
        first_name: string | null;
        last_name: string | null;
        email: string | null;
        role_id: number | null;
        staff_id: number | null;
        is_active: boolean;
        must_reset_password: boolean;
        has_password: boolean;
        last_login_at: string | null;
    } | null;
    roles: { id: number; name: string; slug: string }[];
    staff: { id: number; staff_number: string; full_name: string }[];
    presetStaffId: number | null;
};

function TemporaryPassword({ password }: { password: string }) {
    const [copied, copy] = useClipboard();

    return (
        <Alert className="border-amber-500/50 bg-amber-50 text-amber-950 dark:bg-amber-950/30 dark:text-amber-100">
            <KeyRound />
            <AlertTitle>Temporary password — shown once</AlertTitle>
            <AlertDescription className="space-y-3">
                <p>
                    Give this to the user. They must replace it the first time
                    they sign in. It cannot be shown again.
                </p>
                <div className="flex items-center gap-2">
                    <code className="rounded bg-background px-3 py-1.5 font-mono text-base tracking-wider select-all">
                        {password}
                    </code>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => copy(password)}
                    >
                        {copied === password ? <Check /> : <Copy />}
                        {copied === password ? 'Copied' : 'Copy'}
                    </Button>
                </div>
            </AlertDescription>
        </Alert>
    );
}

export default function UserForm({ user, roles, staff, presetStaffId }: Props) {
    const { can } = usePermission();
    const flash = usePage().flash;
    const editing = user !== null;

    const form = useForm({
        username: user?.username ?? '',
        first_name: user?.first_name ?? '',
        last_name: user?.last_name ?? '',
        email: user?.email ?? '',
        role_id: user?.role_id ? String(user.role_id) : '',
        staff_id: user?.staff_id
            ? String(user.staff_id)
            : presetStaffId
              ? String(presetStaffId)
              : '',
        is_active: user?.is_active ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (editing) {
            form.put(update(user.id).url, { preserveScroll: true });
        } else {
            form.post(store().url);
        }
    };

    const chooseStaff = (id: string) => {
        form.setData('staff_id', id);

        // Save typing: pre-fill the name from the staff record when the name fields are still empty.
        const person = staff.find((s) => String(s.id) === id);

        if (person && !form.data.first_name && !form.data.last_name) {
            const [first, ...rest] = person.full_name.trim().split(/\s+/);
            form.setData((data) => ({
                ...data,
                staff_id: id,
                first_name: first ?? '',
                last_name: rest.join(' '),
            }));
        }
    };

    return (
        <>
            <Head title={editing ? `Edit ${user.username}` : 'New User'} />

            <div className="max-w-3xl space-y-6 p-4">
                <PageHeader
                    title={editing ? `Edit ${user.name}` : 'New User'}
                    description={
                        editing
                            ? `@${user.username}`
                            : 'A temporary password is generated for the new account. The user must change it at first sign-in.'
                    }
                />

                {flash.temporaryPassword && (
                    <TemporaryPassword password={flash.temporaryPassword} />
                )}

                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-lg border p-5"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="username">Username</Label>
                        <Input
                            id="username"
                            value={form.data.username}
                            onChange={(e) =>
                                form.setData('username', e.target.value)
                            }
                            autoCapitalize="none"
                            spellCheck={false}
                            required
                        />
                        <p className="text-xs text-muted-foreground">
                            Used to sign in. Letters, numbers, dots, dashes and
                            underscores.
                        </p>
                        <InputError message={form.errors.username} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="first_name">First name</Label>
                            <Input
                                id="first_name"
                                value={form.data.first_name}
                                onChange={(e) =>
                                    form.setData('first_name', e.target.value)
                                }
                                required
                            />
                            <InputError message={form.errors.first_name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="last_name">Last name</Label>
                            <Input
                                id="last_name"
                                value={form.data.last_name}
                                onChange={(e) =>
                                    form.setData('last_name', e.target.value)
                                }
                            />
                            <InputError message={form.errors.last_name} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">Email (optional)</Label>
                        <Input
                            id="email"
                            type="email"
                            value={form.data.email}
                            onChange={(e) =>
                                form.setData('email', e.target.value)
                            }
                        />
                        <InputError message={form.errors.email} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="role_id">Role</Label>
                            <NativeSelect
                                id="role_id"
                                value={form.data.role_id}
                                onChange={(e) =>
                                    form.setData('role_id', e.target.value)
                                }
                                required
                            >
                                <option value="" disabled>
                                    Choose a role…
                                </option>
                                {roles.map((role) => (
                                    <option key={role.id} value={role.id}>
                                        {role.name}
                                    </option>
                                ))}
                            </NativeSelect>
                            <p className="text-xs text-muted-foreground">
                                Decides what this user can do. Transferring
                                their staff record never changes it.
                            </p>
                            <InputError message={form.errors.role_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="staff_id">
                                Linked staff member
                            </Label>
                            <NativeSelect
                                id="staff_id"
                                value={form.data.staff_id}
                                onChange={(e) => chooseStaff(e.target.value)}
                                disabled={!can('staff.link_user')}
                            >
                                <option value="">Not linked</option>
                                {staff.map((person) => (
                                    <option key={person.id} value={person.id}>
                                        {person.full_name} (
                                        {person.staff_number})
                                    </option>
                                ))}
                            </NativeSelect>
                            {!can('staff.link_user') && (
                                <p className="text-xs text-muted-foreground">
                                    You do not have permission to change staff
                                    links.
                                </p>
                            )}
                            <InputError message={form.errors.staff_id} />
                        </div>
                    </div>

                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="is_active"
                            checked={form.data.is_active}
                            onCheckedChange={(checked) =>
                                form.setData('is_active', checked === true)
                            }
                        />
                        <div className="grid gap-1">
                            <Label htmlFor="is_active">Account is active</Label>
                            <p className="text-xs text-muted-foreground">
                                Inactive users cannot sign in and are signed out
                                immediately.
                            </p>
                            <InputError message={form.errors.is_active} />
                        </div>
                    </div>

                    <div className="flex items-center gap-3 border-t pt-5">
                        <Button type="submit" disabled={form.processing}>
                            {editing ? 'Save Changes' : 'Create User'}
                        </Button>
                        <Button asChild variant="ghost">
                            <Link href={index()}>Cancel</Link>
                        </Button>
                    </div>
                </form>

                {editing && can('users.reset_password') && (
                    <section className="space-y-3 rounded-lg border p-5">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="font-medium">Password</h2>
                            {!user.has_password ? (
                                <Badge variant="outline">No password yet</Badge>
                            ) : user.must_reset_password ? (
                                <Badge variant="outline">
                                    Temporary — must be changed
                                </Badge>
                            ) : (
                                <Badge variant="secondary">
                                    Set by the user
                                </Badge>
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Passwords are never visible. To help someone who is
                            locked out, issue a new temporary password; their
                            current one stops working.
                        </p>

                        <Dialog>
                            <DialogTrigger asChild>
                                <Button type="button" variant="outline">
                                    <KeyRound /> Issue Temporary Password
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>
                                        {' '}
                                        Issue a Temporary Password?{' '}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {user.name}'s current password will stop
                                        working immediately. You will see the
                                        new one once.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter>
                                    <DialogClose asChild>
                                        <Button variant="ghost">Cancel</Button>
                                    </DialogClose>
                                    <DialogClose asChild>
                                        <Button
                                            onClick={() =>
                                                router.post(
                                                    resetPassword(user.id).url,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Issue Password
                                        </Button>
                                    </DialogClose>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </section>
                )}
            </div>
        </>
    );
}

UserForm.layout = { breadcrumbs: [{ title: 'Users', href: index() }] };
