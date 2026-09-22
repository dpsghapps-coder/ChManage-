import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';

const initials = (name: string) =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');

/** A person's photo, or their initials when there is none (or the file is missing). */
export function PersonAvatar({
    name,
    photoUrl,
    className,
}: {
    name: string;
    photoUrl: string | null;
    className?: string;
}) {
    return (
        <Avatar className={cn('size-10', className)}>
            {photoUrl && <AvatarImage src={photoUrl} alt={name} />}
            <AvatarFallback className="text-xs font-medium">
                {initials(name)}
            </AvatarFallback>
        </Avatar>
    );
}
