import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowRightLeft,
    Pencil,
    ShieldCheck,
    Trash2,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/staff-status';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { Textarea } from '@/components/ui/textarea';
import { usePermission } from '@/hooks/use-permission';
import { create as createUser, edit as editUser } from '@/routes/admin/users';
import { destroy, edit, index } from '@/routes/staff';
import { store as storeTransfer } from '@/routes/staff/transfers';

type Lookup = { id: number; name: string };

type Transfer = {
    id: number;
    effective_on: string;
    from_department: string | null;
    to_department: string | null;
    from_position: string | null;
    to_position: string | null;
    from_location: string | null;
    to_location: string | null;
    reason: string | null;
    recorded_by: string | null;
};

type Props = {
    staff: {
        id: number;
        staff_number: string;
        title: string | null;
        full_name: string;
        sex: string | null;
        date_of_birth: string | null;
        telephone: string | null;
        email: string | null;
        address: string | null;
        ssnit_number: string | null;
        location: string | null;
        status: string;
        joined_on: string | null;
        left_on: string | null;
        emergency_contact_name: string | null;
        emergency_contact_phone: string | null;
        notes: string | null;
        department_id: number | null;
        position_id: number | null;
        department: string | null;
        position: string | null;
        user: {
            id: number;
            username: string;
            is_active: boolean;
            role: string | null;
            last_login_at: string | null;
        } | null;
        transfers: Transfer[];
    };
    departments: Lookup[];
    positions: Lookup[];
};

const today = () => new Date().toISOString().slice(0, 10);
const dash = <span className="text-muted-foreground">—</span>;

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="mt-0.5 text-sm">{children || dash}</dd>
        </div>
    );
}

function TransferDialog({
    staff,
    departments,
    positions,
}: Pick<Props, 'staff' | 'departments' | 'positions'>) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        to_department_id: '',
        to_position_id: '',
        to_location: '',
        effective_on: today(),
        reason: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.post(storeTransfer(staff.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <ArrowRightLeft /> Transfer
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>Transfer {staff.full_name}</DialogTitle>
                        <DialogDescription>
                            Change one or more of department, position and
                            station. The move is recorded in the history.
                            {staff.user &&
                                ' Their user account and role stay exactly as they are.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="to_department_id">Department</Label>
                        <NativeSelect
                            id="to_department_id"
                            value={form.data.to_department_id}
                            onChange={(e) =>
                                form.setData('to_department_id', e.target.value)
                            }
                        >
                            <option value="">
                                Keep current ({staff.department ?? 'none'})
                            </option>
                            {departments
                                .filter((d) => d.id !== staff.department_id)
                                .map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.name}
                                    </option>
                                ))}
                        </NativeSelect>
                        <InputError message={form.errors.to_department_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="to_position_id">Position</Label>
                        <NativeSelect
                            id="to_position_id"
                            value={form.data.to_position_id}
                            onChange={(e) =>
                                form.setData('to_position_id', e.target.value)
                            }
                        >
                            <option value="">
                                Keep current ({staff.position ?? 'none'})
                            </option>
                            {positions
                                .filter((p) => p.id !== staff.position_id)
                                .map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                        </NativeSelect>
                        <InputError message={form.errors.to_position_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="to_location">
                            Station / congregation
                        </Label>
                        <Input
                            id="to_location"
                            value={form.data.to_location}
                            onChange={(e) =>
                                form.setData('to_location', e.target.value)
                            }
                            placeholder={
                                staff.location
                                    ? `Keep current (${staff.location})`
                                    : 'Where they will serve'
                            }
                        />
                        <InputError message={form.errors.to_location} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="effective_on">Effective from</Label>
                        <Input
                            id="effective_on"
                            type="date"
                            value={form.data.effective_on}
                            onChange={(e) =>
                                form.setData('effective_on', e.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.effective_on} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="reason">Reason (optional)</Label>
                        <Textarea
                            id="reason"
                            rows={2}
                            value={form.data.reason}
                            onChange={(e) =>
                                form.setData('reason', e.target.value)
                            }
                        />
                        <InputError message={form.errors.reason} />
                    </div>

                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="ghost">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={form.processing}>
                            Record Transfer
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function Change({ from, to }: { from: string | null; to: string | null }) {
    if (from === to) {
        return (
            <span className="text-muted-foreground">
                {to ?? '—'} (unchanged)
            </span>
        );
    }

    return (
        <span className="inline-flex flex-wrap items-center gap-1.5">
            <span className="text-muted-foreground">{from ?? 'none'}</span>
            <ArrowRight className="size-3.5 text-muted-foreground" />
            <span className="font-medium">{to ?? 'none'}</span>
        </span>
    );
}

export default function StaffShow({ staff, departments, positions }: Props) {
    const { can } = usePermission();
    const errors = usePage().props.errors as Record<string, string> | undefined;

    return (
        <>
            <Head title={staff.full_name} />

            <div className="max-w-5xl space-y-6 p-4">
                <PageHeader
                    title={`${staff.title ? `${staff.title} ` : ''}${staff.full_name}`}
                    description={`${staff.staff_number}${staff.position ? ` · ${staff.position}` : ''}${staff.department ? ` · ${staff.department}` : ''}`}
                    actions={
                        <>
                            <StatusBadge status={staff.status} />
                            {can('staff.transfer') && (
                                <TransferDialog
                                    staff={staff}
                                    departments={departments}
                                    positions={positions}
                                />
                            )}
                            {can('staff.edit') && (
                                <Button asChild variant="outline">
                                    <Link href={edit(staff.id)}>
                                        <Pencil /> Edit
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                {errors?.staff && (
                    <Alert variant="destructive">
                        <AlertDescription>{errors.staff}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <section className="rounded-lg border p-5">
                            <h2 className="mb-4 font-medium">Employment</h2>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Field label="Department">
                                    {staff.department}
                                </Field>
                                <Field label="Position">{staff.position}</Field>
                                <Field label="Station / congregation">
                                    {staff.location}
                                </Field>
                                <Field label="SSNIT number">
                                    {staff.ssnit_number}
                                </Field>
                                <Field label="Date joined">
                                    {staff.joined_on}
                                </Field>
                                <Field label="Date left">{staff.left_on}</Field>
                            </dl>
                        </section>

                        <section className="rounded-lg border p-5">
                            <h2 className="mb-4 font-medium">Contact</h2>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Field label="Telephone">
                                    {staff.telephone}
                                </Field>
                                <Field label="Email">{staff.email}</Field>
                                <Field label="Date of birth">
                                    {staff.date_of_birth}
                                </Field>
                                <Field label="Sex">
                                    {staff.sex
                                        ? staff.sex.charAt(0).toUpperCase() +
                                          staff.sex.slice(1)
                                        : null}
                                </Field>
                                <div className="sm:col-span-2">
                                    <Field label="Address">
                                        {staff.address}
                                    </Field>
                                </div>
                                <Field label="Emergency contact">
                                    {staff.emergency_contact_name}
                                </Field>
                                <Field label="Emergency phone">
                                    {staff.emergency_contact_phone}
                                </Field>
                                {staff.notes && (
                                    <div className="sm:col-span-2">
                                        <Field label="Notes">
                                            {staff.notes}
                                        </Field>
                                    </div>
                                )}
                            </dl>
                        </section>

                        <section className="rounded-lg border p-5">
                            <h2 className="mb-4 font-medium">
                                Transfer History
                            </h2>
                            {staff.transfers.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No transfers recorded.
                                </p>
                            ) : (
                                <ol className="space-y-4">
                                    {staff.transfers.map((t) => (
                                        <li
                                            key={t.id}
                                            className="relative border-l-2 pl-4"
                                        >
                                            <p className="text-sm font-medium">
                                                {t.effective_on}
                                                {t.recorded_by && (
                                                    <span className="font-normal text-muted-foreground">
                                                        {' '}
                                                        · recorded by{' '}
                                                        {t.recorded_by}
                                                    </span>
                                                )}
                                            </p>
                                            <dl className="mt-1 space-y-0.5 text-sm">
                                                <div>
                                                    <span className="text-muted-foreground">
                                                        Department:{' '}
                                                    </span>
                                                    <Change
                                                        from={t.from_department}
                                                        to={t.to_department}
                                                    />
                                                </div>
                                                <div>
                                                    <span className="text-muted-foreground">
                                                        Position:{' '}
                                                    </span>
                                                    <Change
                                                        from={t.from_position}
                                                        to={t.to_position}
                                                    />
                                                </div>
                                                <div>
                                                    <span className="text-muted-foreground">
                                                        Station:{' '}
                                                    </span>
                                                    <Change
                                                        from={t.from_location}
                                                        to={t.to_location}
                                                    />
                                                </div>
                                            </dl>
                                            {t.reason && (
                                                <p className="mt-1 text-sm text-muted-foreground italic">
                                                    “{t.reason}”
                                                </p>
                                            )}
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </section>
                    </div>

                    <aside className="space-y-6">
                        <section className="space-y-3 rounded-lg border p-5">
                            <h2 className="flex items-center gap-2 font-medium">
                                <ShieldCheck className="size-4" /> User Account
                            </h2>

                            {staff.user ? (
                                <>
                                    <dl className="space-y-3">
                                        <Field label="Username">
                                            @{staff.user.username}
                                        </Field>
                                        <Field label="Role">
                                            {staff.user.role ? (
                                                <Badge variant="outline">
                                                    {staff.user.role}
                                                </Badge>
                                            ) : null}
                                        </Field>
                                        <Field label="Status">
                                            {staff.user.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Field>
                                        <Field label="Last sign-in">
                                            {staff.user.last_login_at
                                                ? new Date(
                                                      staff.user.last_login_at,
                                                  ).toLocaleString()
                                                : 'Never'}
                                        </Field>
                                    </dl>
                                    {can('users.edit') && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                            className="w-full"
                                        >
                                            <Link
                                                href={editUser(staff.user.id)}
                                            >
                                                Manage Account
                                            </Link>
                                        </Button>
                                    )}
                                </>
                            ) : (
                                <>
                                    <p className="text-sm text-muted-foreground">
                                        No login yet.
                                    </p>
                                    {can('users.create') &&
                                        can('staff.link_user') &&
                                        staff.status !== 'terminated' && (
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                                className="w-full"
                                            >
                                                <Link
                                                    href={createUser({
                                                        query: {
                                                            staff_id: staff.id,
                                                        },
                                                    })}
                                                >
                                                    <UserPlus /> Create Login
                                                </Link>
                                            </Button>
                                        )}
                                </>
                            )}

                            <p className="border-t pt-3 text-xs text-muted-foreground">
                                Transferring this person changes their
                                department, position or station only. The
                                account and its role never change.
                            </p>
                        </section>

                        {can('staff.delete') && !staff.user && (
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="text-destructive hover:text-destructive"
                                    >
                                        <Trash2 /> Delete Record
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Delete {staff.full_name}?
                                        </DialogTitle>
                                        <DialogDescription>
                                            This removes the record and its
                                            transfer history. If they have left,
                                            set their status to Terminated
                                            instead.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <DialogFooter>
                                        <DialogClose asChild>
                                            <Button variant="ghost">
                                                Cancel
                                            </Button>
                                        </DialogClose>
                                        <DialogClose asChild>
                                            <Button
                                                variant="destructive"
                                                onClick={() =>
                                                    router.delete(
                                                        destroy(staff.id).url,
                                                    )
                                                }
                                            >
                                                Delete
                                            </Button>
                                        </DialogClose>
                                    </DialogFooter>
                                </DialogContent>
                            </Dialog>
                        )}
                    </aside>
                </div>
            </div>
        </>
    );
}

StaffShow.layout = {
    breadcrumbs: [{ title: 'Staff Directory', href: index() }],
};
