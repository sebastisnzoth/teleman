import { DashboardFont, useFont } from '@/hooks/use-font';
import { cn } from '@/lib/utils';
import { Type } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const fontOptions: { value: DashboardFont; label: string; description: string }[] = [
    { value: 'inter', label: 'Inter', description: 'Modern, clean sans-serif (Default)' },
    { value: 'instrument-sans', label: 'Instrument Sans', description: 'Elegant sans-serif' },
    { value: 'geist-mono', label: 'Geist Mono', description: 'Monospace for technical look' },
    { value: 'system-ui', label: 'System UI', description: 'Native system font' },
    { value: 'arial', label: 'Arial', description: 'Classic sans-serif' },
    { value: 'georgia', label: 'Georgia', description: 'Traditional serif' },
];

export default function FontSelector() {
    const { font, updateFont } = useFont();

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Type className="h-5 w-5" />
                    Dashboard Font
                </CardTitle>
                <CardDescription>
                    Choose the font style for your dashboard. This setting only affects the dashboard page.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="space-y-2">
                    <Label htmlFor="font-select">Font Family</Label>
                    <Select value={font} onValueChange={(value) => updateFont(value as DashboardFont)}>
                        <SelectTrigger id="font-select" className="w-full">
                            <SelectValue placeholder="Select a font" />
                        </SelectTrigger>
                        <SelectContent>
                            {fontOptions.map((option) => (
                                <SelectItem 
                                    key={option.value} 
                                    value={option.value}
                                    className={cn(
                                        "cursor-pointer",
                                        font === option.value && "bg-accent"
                                    )}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {/* Preview */}
                <div className="space-y-2">
                    <Label>Preview</Label>
                    <div 
                        className="rounded-md border p-4 bg-card"
                        style={{ 
                            fontFamily: font === 'inter' 
                                ? "'Inter', ui-sans-serif, system-ui, sans-serif"
                                : font === 'instrument-sans'
                                ? "'Instrument Sans', ui-sans-serif, system-ui, sans-serif"
                                : font === 'geist-mono'
                                ? "'Geist Mono', ui-monospace, monospace"
                                : font === 'system-ui'
                                ? "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"
                                : font === 'arial'
                                ? "Arial, Helvetica, sans-serif"
                                : "Georgia, 'Times New Roman', serif"
                        }}
                    >
                        <h3 className="text-lg font-semibold mb-2">Dashboard Preview</h3>
                        <p className="text-sm text-muted-foreground">
                            The quick brown fox jumps over the lazy dog. 0123456789
                        </p>
                        <p className="text-xs text-muted-foreground mt-2">
                            This is how text will appear on your dashboard with the selected font.
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
