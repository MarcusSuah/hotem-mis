import { Head, useForm } from '@inertiajs/react';
import { Field } from '@/components/admin/field';
import { FormErrorSummary } from '@/components/admin/form-error-summary';
import { ImagePicker } from '@/components/admin/image-picker';
import { PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { fieldError, notifyValidationFailed } from '@/lib/form';
import admin from '@/routes/admin';

type Settings = {
    hotel: {
        name: string;
        tagline: string | null;
        email: string;
        phone: string;
        address: string;
    };
    website: {
        hero_eyebrow: string | null;
        hero_title: string;
        hero_subtitle: string | null;
        about_title: string | null;
        about_body: string | null;
        booking_note: string | null;
    };
    social: {
        facebook: string | null;
        instagram: string | null;
        x: string | null;
        tripadvisor: string | null;
    };
    seo: { meta_title: string | null; meta_description: string | null };
};

function clean<T extends Record<string, unknown>>(
    values: T,
): Record<keyof T, string> {
    return Object.fromEntries(
        Object.entries(values).map(([key, value]) => [key, value ?? '']),
    ) as Record<keyof T, string>;
}

export default function WebsiteSettings({
    settings,
    heroImageUrl,
}: {
    settings: Settings;
    heroImageUrl: string | null;
}) {
    const form = useForm({
        hotel: clean(settings.hotel),
        website: clean(settings.website),
        social: clean(settings.social),
        seo: clean(settings.seo),
        hero_image: null as File | null,
    });
    const { data, errors } = form;
    const err = (key: string) =>
        (errors as Record<string, string | undefined>)[key];

    function update(
        group: 'hotel' | 'website' | 'social' | 'seo',
        key: string,
        value: string,
    ) {
        form.setData((previous) => ({
            ...previous,
            [group]: { ...previous[group], [key]: value },
        }));
    }

    function text(
        group: 'hotel' | 'website' | 'social' | 'seo',
        key: string,
        label: string,
        options: {
            required?: boolean;
            multiline?: boolean;
            placeholder?: string;
        } = {},
    ) {
        const id = `${group}-${key}`;
        const value = (data[group] as Record<string, string>)[key] ?? '';

        return (
            <Field
                label={label}
                htmlFor={id}
                error={err(`${group}.${key}`)}
                required={options.required}
            >
                {options.multiline ? (
                    <Textarea
                        id={id}
                        rows={4}
                        value={value}
                        placeholder={options.placeholder}
                        onChange={(event) =>
                            update(group, key, event.target.value)
                        }
                    />
                ) : (
                    <Input
                        id={id}
                        value={value}
                        placeholder={options.placeholder}
                        onChange={(event) =>
                            update(group, key, event.target.value)
                        }
                    />
                )}
            </Field>
        );
    }

    return (
        <>
            <Head title="Website settings" />
            <form
                className="flex flex-1 flex-col gap-6 p-4 md:p-6"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.transform((values) => ({ ...values, _method: 'put' }));
                    form.post(admin.website.settings.update.url(), {
                        forceFormData: true,
                        preserveScroll: true,
                        onError: notifyValidationFailed,
                        onSuccess: () => form.setData('hero_image', null),
                    });
                }}
            >
                <PageHeader
                    title="Website settings"
                    description="Hotel details, homepage text, social links and search engine defaults."
                    actions={
                        <Button type="submit" disabled={form.processing}>
                            Save settings
                        </Button>
                    }
                />
                <FormErrorSummary errors={errors} />

                <section className="grid gap-4 rounded-xl border bg-card p-6 shadow-xs md:grid-cols-2">
                    <h2 className="font-display text-xl font-semibold md:col-span-2">
                        Hotel
                    </h2>
                    {text('hotel', 'name', 'Hotel name', { required: true })}
                    {text('hotel', 'tagline', 'Tagline')}
                    {text('hotel', 'email', 'Public email', { required: true })}
                    {text('hotel', 'phone', 'Public phone', { required: true })}
                    <div className="md:col-span-2">
                        {text('hotel', 'address', 'Address', {
                            required: true,
                        })}
                    </div>
                </section>

                <section className="grid gap-4 rounded-xl border bg-card p-6 shadow-xs md:grid-cols-2">
                    <h2 className="font-display text-xl font-semibold md:col-span-2">
                        Homepage
                    </h2>
                    {text('website', 'hero_eyebrow', 'Hero eyebrow')}
                    {text('website', 'hero_title', 'Hero headline', {
                        required: true,
                    })}
                    <div className="md:col-span-2">
                        {text('website', 'hero_subtitle', 'Hero text', {
                            multiline: true,
                        })}
                    </div>
                    <div className="md:col-span-2">
                        {heroImageUrl && (
                            <img
                                src={heroImageUrl}
                                alt="Current hero"
                                className="mb-3 h-32 rounded-lg object-cover"
                            />
                        )}
                        <ImagePicker
                            label={
                                heroImageUrl
                                    ? 'Replace hero image'
                                    : 'Hero image'
                            }
                            files={data.hero_image ? [data.hero_image] : []}
                            onFilesChange={(files) =>
                                form.setData('hero_image', files[0] ?? null)
                            }
                            error={fieldError(errors, 'hero_image')}
                        />
                    </div>
                    {text('website', 'about_title', 'Welcome heading')}
                    <div className="md:col-span-2">
                        {text(
                            'website',
                            'about_body',
                            'Welcome text (Markdown)',
                            { multiline: true },
                        )}
                    </div>
                    <div className="md:col-span-2">
                        {text(
                            'website',
                            'booking_note',
                            'Note shown on booking requests',
                            { multiline: true },
                        )}
                    </div>
                </section>

                <section className="grid gap-4 rounded-xl border bg-card p-6 shadow-xs md:grid-cols-2">
                    <h2 className="font-display text-xl font-semibold md:col-span-2">
                        Social & search
                    </h2>
                    {text('social', 'facebook', 'Facebook URL', {
                        placeholder: 'https://',
                    })}
                    {text('social', 'instagram', 'Instagram URL', {
                        placeholder: 'https://',
                    })}
                    {text('social', 'x', 'X URL', { placeholder: 'https://' })}
                    {text('social', 'tripadvisor', 'Tripadvisor URL', {
                        placeholder: 'https://',
                    })}
                    {text('seo', 'meta_title', 'Default search title')}
                    {text(
                        'seo',
                        'meta_description',
                        'Default search description',
                    )}
                </section>
            </form>
        </>
    );
}

WebsiteSettings.layout = {
    breadcrumbs: [
        { title: 'Website settings', href: admin.website.settings.edit() },
    ],
};
