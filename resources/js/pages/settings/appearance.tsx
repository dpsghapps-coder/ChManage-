import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="Appearance Settings" />

            <h1 className="sr-only">Appearance Settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Appearance Settings"
                    description="Update the appearance settings for your account"
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Appearance Settings',
            href: editAppearance(),
        },
    ],
};
