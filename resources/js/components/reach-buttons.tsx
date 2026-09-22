import { MessageCircle, Phone } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { callUrl, canWhatsApp, whatsappUrl } from '@/lib/contact';

/** Call and WhatsApp buttons for one number. WhatsApp only shows for mobile lines. */
export function ReachButtons({ phone, name }: { phone: string; name: string }) {
    return (
        <div className="flex items-center gap-1.5">
            <Button size="sm" variant="outline" asChild>
                <a
                    href={callUrl(phone)}
                    aria-label={`Call ${name} on ${phone}`}
                >
                    <Phone /> Call
                </a>
            </Button>
            {canWhatsApp(phone) && (
                <Button
                    size="sm"
                    variant="outline"
                    className="text-emerald-700 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-400"
                    asChild
                >
                    <a
                        href={whatsappUrl(phone)}
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label={`WhatsApp ${name} on ${phone}`}
                    >
                        <MessageCircle /> WhatsApp
                    </a>
                </Button>
            )}
        </div>
    );
}
