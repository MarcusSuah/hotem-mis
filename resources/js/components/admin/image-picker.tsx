import { ImagePlus, X } from 'lucide-react';
import { useEffect, useId, useMemo } from 'react';
import { cn } from '@/lib/utils';

export type ExistingImage = {
    id: number;
    url: string;
    alt_text: string | null;
};

/**
 * Selects new images with instant previews, and marks existing images for
 * removal. Nothing is uploaded until the surrounding form is submitted.
 */
export function ImagePicker({
    label,
    multiple = false,
    files,
    onFilesChange,
    existing = [],
    removedIds = [],
    onToggleRemove,
    error,
}: {
    label: string;
    multiple?: boolean;
    files: File[];
    onFilesChange: (files: File[]) => void;
    existing?: ExistingImage[];
    removedIds?: number[];
    onToggleRemove?: (id: number) => void;
    error?: string;
}) {
    const inputId = useId();
    const previews = useMemo(
        () => files.map((file) => ({ file, url: URL.createObjectURL(file) })),
        [files],
    );

    useEffect(
        () => () =>
            previews.forEach((preview) => URL.revokeObjectURL(preview.url)),
        [previews],
    );

    function handleSelect(selected: FileList | null) {
        const list = Array.from(selected ?? []);
        onFilesChange(multiple ? [...files, ...list] : list.slice(0, 1));
    }

    return (
        <div className="grid gap-2">
            <span className="text-sm font-medium">{label}</span>
            <div className="grid grid-cols-3 gap-3 sm:grid-cols-4">
                {existing.map((image) => {
                    const removed = removedIds.includes(image.id);

                    return (
                        <div
                            key={`existing-${image.id}`}
                            className={cn(
                                'group relative aspect-[4/3] overflow-hidden rounded-lg border',
                                removed && 'opacity-40',
                            )}
                        >
                            <img
                                src={image.url}
                                alt={image.alt_text ?? ''}
                                className="size-full object-cover"
                                loading="lazy"
                            />
                            {onToggleRemove && (
                                <button
                                    type="button"
                                    onClick={() => onToggleRemove(image.id)}
                                    className="absolute top-1 right-1 rounded-full bg-black/60 p-1 text-white hover:bg-black/80"
                                    aria-label={
                                        removed ? 'Keep image' : 'Remove image'
                                    }
                                >
                                    <X className="size-3.5" />
                                </button>
                            )}
                            {removed && (
                                <span className="absolute inset-x-0 bottom-0 bg-destructive/90 py-0.5 text-center text-[10px] font-medium text-white">
                                    Will be removed
                                </span>
                            )}
                        </div>
                    );
                })}
                {previews.map((preview, index) => (
                    <div
                        key={preview.url}
                        className="relative aspect-[4/3] overflow-hidden rounded-lg border border-highlight/60"
                    >
                        <img
                            src={preview.url}
                            alt=""
                            className="size-full object-cover"
                        />
                        <button
                            type="button"
                            onClick={() =>
                                onFilesChange(
                                    files.filter(
                                        (_, position) => position !== index,
                                    ),
                                )
                            }
                            className="absolute top-1 right-1 rounded-full bg-black/60 p-1 text-white hover:bg-black/80"
                            aria-label={`Remove ${preview.file.name}`}
                        >
                            <X className="size-3.5" />
                        </button>
                        <span className="absolute inset-x-0 bottom-0 bg-highlight/90 py-0.5 text-center text-[10px] font-medium text-highlight-foreground">
                            New
                        </span>
                    </div>
                ))}
                {(multiple || files.length === 0) && (
                    <label
                        htmlFor={inputId}
                        className="flex aspect-[4/3] cursor-pointer flex-col items-center justify-center gap-1 rounded-lg border border-dashed text-xs text-muted-foreground transition-colors hover:border-primary hover:text-primary"
                    >
                        <ImagePlus className="size-5" />
                        {multiple ? 'Add images' : 'Choose image'}
                        <input
                            id={inputId}
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            multiple={multiple}
                            className="sr-only"
                            onChange={(event) => {
                                handleSelect(event.target.files);
                                event.target.value = '';
                            }}
                        />
                    </label>
                )}
            </div>
            <p className="text-xs text-muted-foreground">
                JPG, PNG or WebP, up to 5 MB each.
            </p>
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}
