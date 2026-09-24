import { Link } from '@inertiajs/react';
import { PersonAvatar } from '@/components/person-avatar';
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

function Row({ nodes }: { nodes: Node[] }) {
    if (nodes.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap justify-center gap-3">
            {nodes.map((node, i) => (
                <NodeCard key={i} node={node} />
            ))}
        </div>
    );
}

const Connector = () => <div className="h-5 w-px bg-border" aria-hidden />;

/**
 * A simple family-tree diagram built from whatever links exist for this member: parents, emergency
 * contact and next of kin (each linked to their own member record when they are one), and children:
 * adult members who name this member as a parent, and the Children Service / Junior Youth register. Sections with nothing recorded are left out; the whole tree hides if there is
 * no family information at all.
 */
export function FamilyTree({
    member,
    related,
}: {
    member: MemberDetail;
    related: RelatedData;
}) {
    const parents: Node[] = [
        ...(member.father_name
            ? [
                  {
                      label: 'Father',
                      name: member.father_name,
                      linkedMemberId: member.father_member?.id ?? null,
                  },
              ]
            : []),
        ...(member.mother_name
            ? [
                  {
                      label: 'Mother',
                      name: member.mother_name,
                      linkedMemberId: member.mother_member?.id ?? null,
                  },
              ]
            : []),
    ];

    const contacts: Node[] = [
        ...(related.emergency_contact.name || related.emergency_contact.member
            ? [
                  {
                      label: 'Emergency Contact',
                      name:
                          related.emergency_contact.member?.full_name ??
                          related.emergency_contact.name,
                      subtitle: related.emergency_contact.relationship,
                      linkedMemberId:
                          related.emergency_contact.member?.id ?? null,
                  },
              ]
            : []),
        ...(related.next_of_kin.name || related.next_of_kin.member
            ? [
                  {
                      label: 'Next of Kin',
                      name:
                          related.next_of_kin.member?.full_name ??
                          related.next_of_kin.name,
                      linkedMemberId: related.next_of_kin.member?.id ?? null,
                  },
              ]
            : []),
    ];

    const children: Node[] = [
        ...related.adult_children.map((child) => ({
            label: child.relationship,
            name: child.name,
            photoUrl: child.photo_url,
            linkedMemberId: child.id,
        })),
        ...related.children.map((child) => ({
            label: child.relationship || 'Child',
            name: child.name,
            photoUrl: child.photo_url,
            linkedYoungId: child.id,
        })),
    ];

    if (parents.length + contacts.length + children.length === 0) {
        return null;
    }

    return (
        <div className="space-y-4 rounded-lg border p-4">
            <h3 className="font-medium">Family Tree</h3>
            <div className="flex flex-col items-center gap-2 overflow-x-auto py-1">
                <Row nodes={parents} />
                {parents.length > 0 && <Connector />}

                <NodeCard
                    node={{
                        label: 'Member',
                        name: member.full_name,
                        subtitle: member.member_number,
                        photoUrl: member.photo_url,
                    }}
                />

                {(contacts.length > 0 || children.length > 0) && <Connector />}
                <Row nodes={contacts} />

                {children.length > 0 && contacts.length > 0 && <Connector />}
                <Row nodes={children} />
            </div>
        </div>
    );
}
