import { Head } from '@inertiajs/react';
import { Hammer } from 'lucide-react';
import { PageHeader } from '@/components/page-header';

type Props = {
    section: string;
    title: string;
    description: string;
    path: string;
};

/** A module that is in the menu but not built yet (see config/modules.php). */
export default function ComingSoon({ title, description }: Props) {
    return (
        <>
            <Head title={title} />

            <div className="max-w-5xl space-y-6 p-4">
                <PageHeader title={title} description={description} />

                <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed py-16 text-center text-muted-foreground">
                    <Hammer className="size-8" />
                    <p className="text-sm">This page is coming soon.</p>
                </div>
            </div>
        </>
    );
}

ComingSoon.layout = ({ section, title, path }: Props) => ({
    breadcrumbs: [
        { title: section, href: path },
        { title, href: path },
    ],
});
