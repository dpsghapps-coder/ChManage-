import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Dialog,
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
import { digitsOnly, phoneInputProps } from '@/lib/phone';
import { index, show, store, update } from '@/routes/staff';
import { store as storeDepartment } from '@/routes/staff/departments';
import { store as storePosition } from '@/routes/staff/positions';
import { statusLabel } from '@/components/staff-status';

type Lookup = { id: number; name: string };

type StaffData = {
    id: number;
    staff_number: string | null;
    title: string | null;
    full_name: string;
    sex: string | null;
    date_of_birth: string | null;
    telephone: string | null;
    email: string | null;
    address: string | null;
    ssnit_number: string | null;
    department_id: number | null;
    position_id: number | null;
    location: string | null;
    status: string;
    joined_on: string | null;
    left_on: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    notes: string | null;
};

type Props = {
    staff: StaffData | null;
    nextStaffNumber: string | null;
    departments: Lookup[];
    positions: Lookup[];
    statuses: string[];
};

/** "+ Add" next to a dropdown: creates the department/position without leaving the form. */
function AddLookup({
    label,
    url,
    onDone,
}: {
    label: string;
    url: string;
    onDone: () => void;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        // Stop the surrounding staff form from also submitting.
        event.stopPropagation();

        form.post(url, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
                onDone();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label={`Add ${label}`}
                    title={`Add ${label}`}
                >
                    <Plus />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>Add {label}</DialogTitle>
                        <DialogDescription>
                            It becomes available in every staff list straight
                            away.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="lookup-name">Name</Label>
                        <Input
                            id="lookup-name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            autoFocus
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Add {label}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function StaffForm({
    staff,
    nextStaffNumber,
    departments,
    positions,
    statuses,
}: Props) {
    const editing = staff !== null;

    const form = useForm({
        staff_number: staff?.staff_number ?? '',
        title: staff?.title ?? '',
        full_name: staff?.full_name ?? '',
        sex: staff?.sex ?? '',
        date_of_birth: staff?.date_of_birth ?? '',
        telephone: staff?.telephone ?? '',
        email: staff?.email ?? '',
        address: staff?.address ?? '',
        ssnit_number: staff?.ssnit_number ?? '',
        department_id: staff?.department_id ? String(staff.department_id) : '',
        position_id: staff?.position_id ? String(staff.position_id) : '',
        location: staff?.location ?? '',
        status: staff?.status ?? 'active',
        joined_on: staff?.joined_on ?? '',
        left_on: staff?.left_on ?? '',
        emergency_contact_name: staff?.emergency_contact_name ?? '',
        emergency_contact_phone: staff?.emergency_contact_phone ?? '',
        notes: staff?.notes ?? '',
    });

    const refreshLookups = () =>
        router.reload({ only: ['departments', 'positions'] });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (editing) {
            form.put(update(staff.id).url);
        } else {
            form.post(store().url);
        }
    };

    const text = (
        name: keyof typeof form.data,
        label: string,
        props: React.ComponentProps<'input'> = {},
    ) => {
        const isPhone =
            name === 'telephone' || name === 'emergency_contact_phone';

        return (
            <div className="grid gap-2">
                <Label htmlFor={name}>{label}</Label>
                <Input
                    id={name}
                    value={form.data[name] as string}
                    onChange={(e) =>
                        form.setData(
                            name,
                            isPhone
                                ? digitsOnly(e.target.value)
                                : e.target.value,
                        )
                    }
                    {...(isPhone ? phoneInputProps : {})}
                    {...props}
                />
                <InputError message={form.errors[name]} />
            </div>
        );
    };

    return (
        <>
            <Head title={editing ? `Edit ${staff.full_name}` : 'Add Staff'} />

            <form onSubmit={submit} className="max-w-4xl space-y-6 p-4">
                <PageHeader
                    title={
                        editing ? `Edit ${staff.full_name}` : 'Add Staff Member'
                    }
                />

                <section className="grid gap-5 rounded-lg border p-5 sm:grid-cols-2">
                    <h2 className="font-medium sm:col-span-2">
                        Personal Details
                    </h2>
                    <div className="grid gap-2">
                        <Label htmlFor="staff_number">Staff number</Label>
                        <Input
                            id="staff_number"
                            value={form.data.staff_number}
                            onChange={(e) =>
                                form.setData('staff_number', e.target.value)
                            }
                            placeholder={
                                nextStaffNumber
                                    ? `${nextStaffNumber} (automatic)`
                                    : undefined
                            }
                        />
                        <InputError message={form.errors.staff_number} />
                    </div>
                    {text('title', 'Title', {
                        placeholder: 'Mr, Mrs, Rev …',
                        maxLength: 20,
                    })}
                    <div className="sm:col-span-2">
                        {text('full_name', 'Full name', {
                            required: true,
                            placeholder: 'Surname Firstname Othernames',
                        })}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="sex">Sex</Label>
                        <NativeSelect
                            id="sex"
                            value={form.data.sex}
                            onChange={(e) =>
                                form.setData('sex', e.target.value)
                            }
                        >
                            <option value="">Not stated</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </NativeSelect>
                        <InputError message={form.errors.sex} />
                    </div>
                    {text('date_of_birth', 'Date of birth', { type: 'date' })}
                    {text('telephone', 'Telephone')}
                    {text('email', 'Email', { type: 'email' })}
                    {text('ssnit_number', 'SSNIT number')}
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="address">Address</Label>
                        <Textarea
                            id="address"
                            rows={2}
                            value={form.data.address}
                            onChange={(e) =>
                                form.setData('address', e.target.value)
                            }
                        />
                        <InputError message={form.errors.address} />
                    </div>
                </section>

                <section className="grid gap-5 rounded-lg border p-5 sm:grid-cols-2">
                    <div className="sm:col-span-2">
                        <h2 className="font-medium">Employment</h2>
                        {editing && (
                            <p className="mt-1 text-sm text-muted-foreground">
                                Department, position and station are changed
                                with <strong>Transfer</strong> on the staff
                                page, so the move is kept in the history.
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="department_id">Department</Label>
                        <div className="flex gap-2">
                            <NativeSelect
                                id="department_id"
                                value={form.data.department_id}
                                onChange={(e) =>
                                    form.setData(
                                        'department_id',
                                        e.target.value,
                                    )
                                }
                                disabled={editing}
                            >
                                <option value="">None</option>
                                {departments.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.name}
                                    </option>
                                ))}
                            </NativeSelect>
                            {!editing && (
                                <AddLookup
                                    label="department"
                                    url={storeDepartment().url}
                                    onDone={refreshLookups}
                                />
                            )}
                        </div>
                        <InputError message={form.errors.department_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="position_id">Position</Label>
                        <div className="flex gap-2">
                            <NativeSelect
                                id="position_id"
                                value={form.data.position_id}
                                onChange={(e) =>
                                    form.setData('position_id', e.target.value)
                                }
                                disabled={editing}
                            >
                                <option value="">None</option>
                                {positions.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </NativeSelect>
                            {!editing && (
                                <AddLookup
                                    label="position"
                                    url={storePosition().url}
                                    onDone={refreshLookups}
                                />
                            )}
                        </div>
                        <InputError message={form.errors.position_id} />
                    </div>

                    {text('location', 'Station / congregation', {
                        disabled: editing,
                        placeholder: 'Where they currently serve',
                    })}

                    <div className="grid gap-2">
                        <Label htmlFor="status">Status</Label>
                        <NativeSelect
                            id="status"
                            value={form.data.status}
                            onChange={(e) =>
                                form.setData('status', e.target.value)
                            }
                            required
                        >
                            {statuses.map((s) => (
                                <option key={s} value={s}>
                                    {statusLabel(s)}
                                </option>
                            ))}
                        </NativeSelect>
                        {form.data.status === 'terminated' &&
                            staff?.status !== 'terminated' && (
                                <p className="text-xs text-amber-700 dark:text-amber-400">
                                    Saving will also deactivate this person's
                                    user account, if they have one.
                                </p>
                            )}
                        <InputError message={form.errors.status} />
                    </div>

                    {text('joined_on', 'Date joined', { type: 'date' })}
                    {text('left_on', 'Date left', { type: 'date' })}
                </section>

                <section className="grid gap-5 rounded-lg border p-5 sm:grid-cols-2">
                    <h2 className="font-medium sm:col-span-2">
                        Emergency Contact & Notes
                    </h2>
                    {text('emergency_contact_name', 'Contact name')}
                    {text('emergency_contact_phone', 'Contact phone')}
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="notes">Notes</Label>
                        <Textarea
                            id="notes"
                            rows={3}
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                </section>

                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={form.processing}>
                        {editing ? 'Save Changes' : 'Add to Directory'}
                    </Button>
                    <Button asChild variant="ghost">
                        <Link href={editing ? show(staff.id) : index()}>
                            Cancel
                        </Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

StaffForm.layout = {
    breadcrumbs: [{ title: 'Staff Directory', href: index() }],
};
