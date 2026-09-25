import { router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import type { ActionRow } from '@/components/meetings-nav';
import { PersonField } from '@/components/person-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { destroy, store, update } from '@/routes/actions';

const today = () => new Date().toISOString().slice(0, 10);

/** Adds an action under a decision, or edits one: what, who, by when, and where it stands. */
export function ActionDialog({
    decisionId,
    action,
    statuses,
    onClose,
}: {
    decisionId: number;
    action: ActionRow | null;
    statuses: Record<string, string>;
    onClose: () => void;
}) {
    const [confirming, setConfirming] = useState(false);
    const form = useForm({
        description: action?.description ?? '',
        responsible_member_id: (action?.responsible_member_id ?? '') as
            | number
            | '',
        responsible_name:
            action && !action.responsible_member_id
                ? (action.responsible ?? '')
                : '',
        deadline: action?.deadline ?? '',
        status: action?.status ?? 'pending',
        completed_on: action?.completed_on ?? '',
        note: action?.note ?? '',
    });
    const { data, setData, errors } = form;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (action) {
            form.put(update(action.id).url, options);
        } else {
            form.post(store(decisionId).url, options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto">
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {action ? 'Edit Action' : 'Add Action'}
                        </DialogTitle>
                        <DialogDescription>
                            What has to be done as a result of this decision, by
                            whom and by when.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="action-description">
                            What has to be done
                        </Label>
                        <Textarea
                            id="action-description"
                            rows={3}
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                            maxLength={2000}
                            required
                        />
                        <InputError message={errors.description} />
                    </div>

                    <PersonField
                        id="responsible"
                        label="Person responsible"
                        value={{
                            member_id: data.responsible_member_id,
                            name: data.responsible_name,
                        }}
                        shown={
                            action?.responsible_member_id
                                ? {
                                      full_name: action.responsible ?? '',
                                      member_number: '',
                                  }
                                : null
                        }
                        onChange={(v) =>
                            setData((d) => ({
                                ...d,
                                responsible_member_id: v.member_id,
                                responsible_name: v.name,
                            }))
                        }
                        error={
                            errors.responsible_member_id ??
                            errors.responsible_name
                        }
                    />

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="action-deadline">Deadline</Label>
                            <Input
                                id="action-deadline"
                                type="date"
                                value={data.deadline}
                                onChange={(e) =>
                                    setData('deadline', e.target.value)
                                }
                            />
                            <InputError message={errors.deadline} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="action-status">Status</Label>
                            <NativeSelect
                                id="action-status"
                                value={data.status}
                                onChange={(e) =>
                                    setData((d) => ({
                                        ...d,
                                        status: e.target
                                            .value as ActionRow['status'],
                                        completed_on:
                                            e.target.value === 'completed'
                                                ? d.completed_on || today()
                                                : '',
                                    }))
                                }
                            >
                                {Object.entries(statuses).map(
                                    ([key, label]) => (
                                        <option key={key} value={key}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </NativeSelect>
                            <p className="text-xs text-muted-foreground">
                                Overdue is shown automatically once the deadline
                                has passed.
                            </p>
                        </div>
                    </div>

                    {data.status === 'completed' && (
                        <div className="grid gap-2">
                            <Label htmlFor="action-completed">
                                Completed on
                            </Label>
                            <Input
                                id="action-completed"
                                type="date"
                                max={today()}
                                value={data.completed_on}
                                onChange={(e) =>
                                    setData('completed_on', e.target.value)
                                }
                                className="w-auto"
                            />
                            <InputError message={errors.completed_on} />
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="action-note">Progress note</Label>
                        <Input
                            id="action-note"
                            value={data.note}
                            onChange={(e) => setData('note', e.target.value)}
                            maxLength={500}
                        />
                    </div>

                    <DialogFooter className="gap-2 sm:justify-between">
                        {action ? (
                            confirming ? (
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-sm">
                                        Remove this action?
                                    </span>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        onClick={() =>
                                            router.delete(
                                                destroy(action.id).url,
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: onClose,
                                                },
                                            )
                                        }
                                    >
                                        Yes, remove
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setConfirming(false)}
                                    >
                                        Keep
                                    </Button>
                                </div>
                            ) : (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="text-destructive hover:text-destructive"
                                    onClick={() => setConfirming(true)}
                                >
                                    <Trash2 /> Remove
                                </Button>
                            )
                        ) : (
                            <span />
                        )}
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onClose}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                Save
                            </Button>
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
