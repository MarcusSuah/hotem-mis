import { useForm, usePage } from '@inertiajs/react';
import { Mail, MapPin, Phone } from 'lucide-react';
import { Field } from '@/components/admin/field';
import { Seo } from '@/components/site/seo';
import { PageHero } from '@/components/site/site-ui';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { notifyValidationFailed } from '@/lib/form';
import site from '@/routes/site';

export default function Contact({
    mapEmbedUrl,
}: {
    mapEmbedUrl: string | null;
}) {
    const { hotel } = usePage().props;
    const form = useForm({
        name: '',
        email: '',
        phone: '',
        subject: '',
        message: '',
        website: '',
    });
    const { data, setData, errors } = form;

    return (
        <>
            <Seo
                title="Contact us"
                description={`Get in touch with ${hotel.name}.`}
            />
            <PageHero
                eyebrow="Contact"
                title="We’d love to hear from you"
                lead="Questions about a stay, an event or a special request — send us a message and our team will reply."
            />
            <section className="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[2fr_3fr]">
                <address className="space-y-4 text-sm not-italic">
                    {hotel.address && (
                        <p className="flex gap-3">
                            <MapPin className="size-5 shrink-0 text-highlight" />{' '}
                            {hotel.address}
                        </p>
                    )}
                    {hotel.phone && (
                        <p className="flex gap-3">
                            <Phone className="size-5 shrink-0 text-highlight" />
                            <a
                                href={`tel:${hotel.phone}`}
                                className="hover:underline"
                            >
                                {hotel.phone}
                            </a>
                        </p>
                    )}
                    {hotel.email && (
                        <p className="flex gap-3">
                            <Mail className="size-5 shrink-0 text-highlight" />
                            <a
                                href={`mailto:${hotel.email}`}
                                className="hover:underline"
                            >
                                {hotel.email}
                            </a>
                        </p>
                    )}
                    {mapEmbedUrl && (
                        <iframe
                            title="Map"
                            src={mapEmbedUrl}
                            className="aspect-[4/3] w-full rounded-xl border"
                            loading="lazy"
                            referrerPolicy="no-referrer-when-downgrade"
                            sandbox="allow-scripts allow-same-origin allow-popups"
                        />
                    )}
                </address>
                <form
                    className="space-y-4 rounded-xl border bg-card p-6 shadow-xs"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(site.contact.store.url(), {
                            preserveScroll: true,
                            onSuccess: () => form.reset(),
                            onError: notifyValidationFailed,
                        });
                    }}
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Name"
                            htmlFor="c-name"
                            error={errors.name}
                            required
                        >
                            <Input
                                id="c-name"
                                autoComplete="name"
                                value={data.name}
                                onChange={(event) =>
                                    setData('name', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Email"
                            htmlFor="c-email"
                            error={errors.email}
                            required
                        >
                            <Input
                                id="c-email"
                                type="email"
                                autoComplete="email"
                                value={data.email}
                                onChange={(event) =>
                                    setData('email', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Phone"
                            htmlFor="c-phone"
                            error={errors.phone}
                        >
                            <Input
                                id="c-phone"
                                type="tel"
                                autoComplete="tel"
                                value={data.phone}
                                onChange={(event) =>
                                    setData('phone', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label="Subject"
                            htmlFor="c-subject"
                            error={errors.subject}
                            required
                        >
                            <Input
                                id="c-subject"
                                value={data.subject}
                                onChange={(event) =>
                                    setData('subject', event.target.value)
                                }
                            />
                        </Field>
                    </div>
                    <Field
                        label="Message"
                        htmlFor="c-message"
                        error={errors.message}
                        required
                    >
                        <Textarea
                            id="c-message"
                            rows={6}
                            value={data.message}
                            onChange={(event) =>
                                setData('message', event.target.value)
                            }
                        />
                    </Field>
                    <div className="hidden" aria-hidden>
                        <label htmlFor="c-website">
                            Leave this field empty
                        </label>
                        <input
                            id="c-website"
                            tabIndex={-1}
                            autoComplete="off"
                            value={data.website}
                            onChange={(event) =>
                                setData('website', event.target.value)
                            }
                        />
                    </div>
                    <Button
                        type="submit"
                        variant="gold"
                        disabled={form.processing}
                    >
                        Send message
                    </Button>
                </form>
            </section>
        </>
    );
}
