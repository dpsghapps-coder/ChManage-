import { useState } from 'react';
import { MemberPicker } from '@/components/member-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type PersonValue = {
    /** Set when the person is a member of the church. */
    member_id: number | '';
    /** Typed when they are not. */
    name: string;
};

/**
 * Someone who is either a member of the church (searched for, so the record is linked) or a name typed in for a
 * person outside it. `shown` is the member's name and number when one is already chosen, so it can be displayed.
 */
export function PersonField({
    id,
    label,
    value,
    shown,
    onChange,
    error,
}: {
    id: string;
    label: string;
    value: PersonValue;
    shown?: { full_name: string; member_number: string } | null;
    onChange: (value: PersonValue) => void;
    error?: string;
}) {
    const [member, setMember] = useState(shown ?? null);

    return (
        <div className="grid content-start gap-2">
            <Label htmlFor={`${id}-name`}>{label}</Label>
            {member && value.member_id !== '' ? (
                <p className="flex flex-wrap items-center gap-2 text-sm">
                    <span className="font-medium">{member.full_name}</span>
                    <span className="text-muted-foreground">
                        {member.member_number}
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            setMember(null);
                            onChange({ member_id: '', name: '' });
                        }}
                    >
                        Change
                    </Button>
                </p>
            ) : (
                <>
                    <MemberPicker
                        invalid={Boolean(error)}
                        onPick={(picked) => {
                            setMember({
                                full_name: picked.full_name,
                                member_number: picked.member_number,
                            });
                            onChange({ member_id: picked.id, name: '' });
                        }}
                    />
                    <Input
                        id={`${id}-name`}
                        value={value.name}
                        onChange={(e) =>
                            onChange({ member_id: '', name: e.target.value })
                        }
                        maxLength={150}
                        placeholder="Or type the name of someone outside the church"
                    />
                </>
            )}
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}
