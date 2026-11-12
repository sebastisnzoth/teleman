import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { HelpCircle, ExternalLink } from 'lucide-react';

interface HelpSection {
    title: string;
    content: string;
}

interface PageHelpProps {
    title: string;
    sections: HelpSection[];
    documentationUrl?: string;
}

export function PageHelp({ title, sections, documentationUrl }: PageHelpProps) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                variant="outline"
                size="icon"
                onClick={() => setOpen(true)}
                className="h-9 w-9"
                title="Help & Documentation"
            >
                <HelpCircle className="h-4 w-4" />
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="text-2xl">{title}</DialogTitle>
                        <DialogDescription>
                            Learn how to use this page effectively
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-6 mt-4">
                        {sections.map((section, index) => (
                            <div key={index} className="space-y-2">
                                <h3 className="font-semibold text-lg">{section.title}</h3>
                                <div className="text-sm text-muted-foreground whitespace-pre-line">
                                    {section.content}
                                </div>
                            </div>
                        ))}
                        
                        {documentationUrl && (
                            <div className="border-t pt-4 mt-6">
                                <a
                                    href={documentationUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
                                >
                                    <ExternalLink className="h-4 w-4" />
                                    View Full Documentation
                                </a>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
