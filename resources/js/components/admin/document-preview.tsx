import { Download, ExternalLink, Printer } from 'lucide-react';
import type { ReactNode } from 'react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

/**
 * Shows a receipt or invoice PDF inside the app so staff can check it before
 * printing it or handing it to the guest.
 */
export function DocumentPreview({ url, title, description, filename, trigger }: { url: string; title: string; description?: string; filename: string; trigger: (open: () => void) => ReactNode }) {
    const [open, setOpen] = useState(false);
    const [loaded, setLoaded] = useState(false);
    const frame = useRef<HTMLIFrameElement>(null);

    function print() {
        const viewer = frame.current?.contentWindow;

        if (viewer) {
            viewer.focus();
            viewer.print();
        } else {
            window.open(url, '_blank', 'noopener');
        }
    }

    return (
        <>
            {trigger(() => {
                setLoaded(false);
                setOpen(true);
            })}
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="flex h-[92vh] max-w-4xl flex-col gap-3 p-4 sm:max-w-4xl">
                    <DialogHeader className="flex-row items-start justify-between gap-4 space-y-0 pr-8">
                        <div className="space-y-1">
                            <DialogTitle className="font-display text-2xl">{title}</DialogTitle>
                            <DialogDescription>{description ?? 'Check the document before printing or sharing it.'}</DialogDescription>
                        </div>
                        <div className="flex shrink-0 flex-wrap justify-end gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <a href={url} target="_blank" rel="noreferrer">
                                    <ExternalLink />
                                    Open
                                </a>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <a href={url} download={filename}>
                                    <Download />
                                    Download
                                </a>
                            </Button>
                            <Button size="sm" onClick={print} disabled={!loaded}>
                                <Printer />
                                Print
                            </Button>
                        </div>
                    </DialogHeader>
                    <div className="relative flex-1 overflow-hidden rounded-lg border bg-muted">
                        {!loaded && (
                            <div className="absolute inset-0 flex items-center justify-center gap-2 text-sm text-muted-foreground">
                                <Spinner /> Preparing document…
                            </div>
                        )}
                        {open && <iframe ref={frame} src={url} title={title} className="size-full bg-white" onLoad={() => setLoaded(true)} />}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
