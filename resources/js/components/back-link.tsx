import { router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

/** Returns to the page the person came from; falls back to the dashboard when this is the first page they opened. */
export function BackLink() {
    const { url } = usePage();

    // The dashboard is the top of the app: there is nothing to go back to.
    if (url.startsWith('/dashboard')) {
        return null;
    }

    const goBack = () => {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            router.visit(dashboard());
        }
    };

    return (
        <Button type="button" variant="ghost" size="sm" onClick={goBack}>
            <ArrowLeft /> Back
        </Button>
    );
}
