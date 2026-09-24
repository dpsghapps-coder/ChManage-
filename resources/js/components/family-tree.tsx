import { Link } from '@inertiajs/react';
import { PersonAvatar } from '@/components/person-avatar';
import { cn } from '@/lib/utils';
import { show as showMember } from '@/routes/members';
import { show as showYoung } from '@/routes/members/young';
import type { MemberDetail, RelatedData } from '@/components/member-related';

type Node = {
    label: string;
    name: string;
    subtitle?: string | null;
    photoUrl?: string | null;
    linkedMemberId?: number | null;
    linkedYoungId?: number | null;
};

function NodeCard({ node }: { node: Node }) {
    const body = (
        <div className="flex w-28 flex-col items-center gap-1 rounded-lg border bg-background p-2.5 text-center shadow-sm transition hover:border-primary/50">
            <PersonAvatar
                name={node.name}
                photoUrl={node.photoUrl ?? null}
                className="size-9"
            />
            <div className="line-clamp-2 text-xs leading-tight font-medium">
                {node.name}
            </div>
            <div className="text-[10px] text-muted-foreground">
                {node.label}
            </div>
            {node.subtitle && (
                <div className="text-[10px] text-muted-foreground">
                    {node.subtitle}
                </div>
            )}
        </div>
    );

    if (node.linkedMemberId) {
        return <Link href={showMember(node.linkedMemberId)}>{body}</Link>;
    }

    if (node.linkedYoungId) {
        return <Link href={showYoung(node.linkedYoungId)}>{body}</Link>;
    }

    return body;
}

function Row({
    nodes,
    align = 'center',
}: {
    nodes: Node[];
    align?: 'start' | 'center' | 'end';
}) {
    if (nodes.length === 0) {
        return null;
    }

    return (
        <div
            className={cn(
                'flex flex-wrap gap-3',
                align === 'start' && 'justify-start',
                align === 'center' && 'justify-center',
                align === 'end' && 'justify-end',
            )}
        >
            {nodes.map((node, i) => (
                <NodeCard key={i} node={node} />
            ))}
        </div>
    );
}

const Connector = () => <div className="h-5 w-px bg-border" aria-hidden />;

/** Relationships that make a next of kin or emergency contact family. Mirrors MemberNextOfKin::FAMILY_RELATIONSHIPS. */
const GENERATION: Record<string, 'grandparents' | 'parents' | 'siblings'> = {
    Grandmother: 'grandparents',
    Grandfather: 'grandparents',
    Mother: 'parents',
    Father: 'parents',
    Aunt: 'parents',
    Uncle: 'parents',
    Brother: 'siblings',
    Sister: 'siblings',
    Sibling: 'siblings',
};

/**
 * The member's family, a generation to a row: grandparents; parents, aunts and uncles; the member in the middle
 * under their parents, with the spouse on the left and brothers and sisters on the right; and children (adult members who name this member as a parent, and the Children Service /
 * Junior Youth register). The next of kin and emergency contact appear only when their relationship makes them
 * family (not a friend, or a relationship typed under "Other"), and never twice. Nodes link to member records. The
 * tree hides when no family is recorded.
 */
export function FamilyTree({
    member,
    related,
}: {
    member: MemberDetail;
    related: RelatedData;
}) {
    const rows: Record<
        'grandparents' | 'parents' | 'spouse' | 'siblings' | 'children',
        Node[]
    > = {
        grandparents: [],
        parents: [],
        spouse: [],
        siblings: [],
        children: [],
    };
    const seen = new Set<string>();

    // The same person is shown once: a linked member by their record, anyone else by name.
    const add = (row: keyof typeof rows, node: Node) => {
        const key = node.linkedMemberId
            ? `member:${node.linkedMemberId}`
            : node.linkedYoungId
              ? `young:${node.linkedYoungId}`
              : `name:${node.name.trim().toLowerCase()}`;

        if (!node.name.trim() || seen.has(key)) {
            return;
        }

        seen.add(key);
        rows[row].push(node);
    };

    if (member.father_name) {
        add('parents', {
            label: 'Father',
            name: member.father_name,
            linkedMemberId: member.father_member?.id ?? null,
        });
    }

    if (member.mother_name) {
        add('parents', {
            label: 'Mother',
            name: member.mother_name,
            linkedMemberId: member.mother_member?.id ?? null,
        });
    }

    // Married members only, as elsewhere.
    if (member.marital_status === 'married' && member.spouse_name) {
        add('spouse', {
            label: 'Spouse',
            name: member.spouse_name,
            linkedMemberId: member.spouse_member?.id ?? null,
        });
    }

    for (const person of [related.next_of_kin, related.emergency_contact]) {
        const generation = GENERATION[person.relationship];
        const name = person.member?.full_name ?? person.name;

        // A second Mother or Father (e.g. the next of kin is the mother already shown) is not added again.
        const parentShown =
            (person.relationship === 'Mother' && member.mother_name) ||
            (person.relationship === 'Father' && member.father_name);

        if (generation && name && !parentShown) {
            add(generation, {
                label: person.relationship,
                name,
                linkedMemberId: person.member?.id ?? null,
            });
        }
    }

    for (const child of related.adult_children) {
        add('children', {
            label: child.relationship,
            name: child.name,
            photoUrl: child.photo_url,
            linkedMemberId: child.id,
        });
    }

    for (const child of related.children) {
        add('children', {
            label: child.relationship || 'Child',
            name: child.name,
            photoUrl: child.photo_url,
            linkedYoungId: child.id,
        });
    }

    const { grandparents, parents, spouse, siblings, children } = rows;

    if (
        grandparents.length +
            parents.length +
            spouse.length +
            siblings.length +
            children.length ===
        0
    ) {
        return null;
    }

    const memberNode: Node = {
        label: 'Member',
        name: member.full_name,
        photoUrl: member.photo_url,
    };

    return (
        <div className="space-y-4 rounded-lg border p-4">
            <h3 className="font-medium">Family Tree</h3>
            <div className="flex flex-col items-center gap-2 overflow-x-auto py-1">
                <Row nodes={grandparents} />
                {grandparents.length > 0 && <Connector />}

                <Row nodes={parents} />
                {parents.length > 0 && <Connector />}

                {/* The member stays centred under the parents: spouse to the left, brothers and sisters to the right. */}
                <div className="grid w-full grid-cols-[1fr_auto_1fr] items-center gap-3">
                    <Row nodes={spouse} align="end" />
                    <NodeCard node={memberNode} />
                    <Row nodes={siblings} align="start" />
                </div>

                {children.length > 0 && <Connector />}
                <Row nodes={children} />
            </div>
        </div>
    );
}
