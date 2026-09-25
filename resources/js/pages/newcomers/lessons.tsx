import { Head, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { NewcomersNav } from '@/components/newcomers-nav';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
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
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/newcomers';
import {
    destroy,
    index as lessonsIndex,
    move,
    store,
    update,
} from '@/routes/newcomers/lessons';

type Lesson = {
    id: number;
    title: string;
    description: string | null;
    is_active: boolean;
};

export default function Lessons({ lessons }: { lessons: Lesson[] }) {
    // `null` closes the dialog; `'new'` adds a lesson.
    const [editing, setEditing] = useState<Lesson | 'new' | null>(null);

    return (
        <>
            <Head title="Lessons" />

            <div className="max-w-5xl space-y-5 p-4">
                <PageHeader
                    title="Lessons"
                    description="The lessons of the newcomers' class, in teaching order. Everyone in the class takes the same lessons."
                    actions={
                        <Button onClick={() => setEditing('new')}>
                            <Plus /> Add Lesson
                        </Button>
                    }
                />
                <NewcomersNav />

                {lessons.length === 0 ? (
                    <p className="rounded-lg border py-10 text-center text-sm text-muted-foreground">
                        No lessons yet. Add the first one.
                    </p>
                ) : (
                    <ol className="divide-y rounded-lg border">
                        {lessons.map((lesson, i) => (
                            <li
                                key={lesson.id}
                                className="flex items-start gap-3 p-4"
                            >
                                <span className="w-7 pt-0.5 text-sm text-muted-foreground tabular-nums">
                                    {i + 1}.
                                </span>
                                <div className="flex-1 space-y-0.5">
                                    <p className="font-medium">
                                        {lesson.title}{' '}
                                        {!lesson.is_active && (
                                            <Badge variant="secondary">
                                                Not in use
                                            </Badge>
                                        )}
                                    </p>
                                    {lesson.description && (
                                        <p className="text-sm text-muted-foreground">
                                            {lesson.description}
                                        </p>
                                    )}
                                </div>
                                <div className="flex gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Move up"
                                        disabled={i === 0}
                                        onClick={() =>
                                            router.put(
                                                move(lesson.id).url,
                                                { direction: 'up' },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <ArrowUp />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Move down"
                                        disabled={i === lessons.length - 1}
                                        onClick={() =>
                                            router.put(
                                                move(lesson.id).url,
                                                { direction: 'down' },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <ArrowDown />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Edit ${lesson.title}`}
                                        onClick={() => setEditing(lesson)}
                                    >
                                        <Pencil />
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ol>
                )}
            </div>

            {editing && (
                <LessonDialog
                    lesson={editing === 'new' ? null : editing}
                    onClose={() => setEditing(null)}
                />
            )}
        </>
    );
}

function LessonDialog({
    lesson,
    onClose,
}: {
    lesson: Lesson | null;
    onClose: () => void;
}) {
    const [confirming, setConfirming] = useState(false);
    const form = useForm({
        title: lesson?.title ?? '',
        description: lesson?.description ?? '',
        is_active: lesson?.is_active ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (lesson) {
            form.put(update(lesson.id).url, options);
        } else {
            form.post(store().url, options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {lesson ? lesson.title : 'Add Lesson'}
                        </DialogTitle>
                        <DialogDescription>
                            {lesson
                                ? 'A lesson not in use is kept but no longer given to new people.'
                                : 'It goes at the end of the list; move it after.'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="lesson-title">Title</Label>
                        <Input
                            id="lesson-title"
                            value={form.data.title}
                            onChange={(e) =>
                                form.setData('title', e.target.value)
                            }
                            maxLength={200}
                            required
                        />
                        <InputError message={form.errors.title} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="lesson-description">
                            What it covers
                        </Label>
                        <Textarea
                            id="lesson-description"
                            rows={3}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                            maxLength={2000}
                        />
                        <InputError message={form.errors.description} />
                    </div>
                    {lesson && (
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.is_active}
                                onChange={(e) =>
                                    form.setData('is_active', e.target.checked)
                                }
                            />
                            In use
                        </label>
                    )}
                    <DialogFooter className="gap-2 sm:justify-between">
                        {lesson ? (
                            confirming ? (
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-sm">
                                        Remove {lesson.title}?
                                    </span>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        onClick={() =>
                                            router.delete(
                                                destroy(lesson.id).url,
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

Lessons.layout = {
    breadcrumbs: [
        { title: 'Newcomers', href: index() },
        { title: 'Lessons', href: lessonsIndex() },
    ],
};
