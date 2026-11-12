import { Button } from '@/components/ui/button';
import {
    CommandDialog,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';

interface SearchResult {
    type: 'menu' | 'customer' | 'agent' | 'campaign' | 'contact' | 'phone' | 'call';
    id: number | string;
    title: string;
    subtitle?: string;
    url: string;
}

export function GlobalSearch() {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResult[]>([]);
    const [loading, setLoading] = useState(false);

    // Keyboard shortcut: Ctrl+K or Cmd+K
    useEffect(() => {
        const down = (e: KeyboardEvent) => {
            if (e.key === 'k' && (e.metaKey || e.ctrlKey)) {
                e.preventDefault();
                setOpen((open) => !open);
            }
        };

        document.addEventListener('keydown', down);
        return () => document.removeEventListener('keydown', down);
    }, []);

    // Reset query when modal is closed
    useEffect(() => {
        if (!open) {
            setQuery('');
            setResults([]);
        }
    }, [open]);

    // Search API call with debounce
    useEffect(() => {
        if (query.length < 2) {
            setResults([]);
            setLoading(false);
            return;
        }

        setLoading(true);
        const timer = setTimeout(() => {
            fetch(`/api/search?q=${encodeURIComponent(query)}`)
                .then((res) => res.json())
                .then((data) => {
                    setResults(data.results || []);
                    setLoading(false);
                })
                .catch(() => {
                    setResults([]);
                    setLoading(false);
                });
        }, 300);

        return () => clearTimeout(timer);
    }, [query]);

    const handleSelect = (url: string) => {
        setOpen(false);
        setQuery('');
        router.visit(url);
    };

    const getTypeLabel = (type: string) => {
        const labels: Record<string, string> = {
            menu: 'Menu',
            customer: 'Customers',
            agent: 'Agents',
            campaign: 'Campaigns',
            contact: 'Contacts',
            phone: 'Phone Numbers',
            call: 'Call Logs',
        };
        return labels[type] || type;
    };

    const getTypeIcon = (type: string) => {
        const icons: Record<string, string> = {
            menu: '🧭',
            customer: '👤',
            agent: '👔',
            campaign: '📢',
            contact: '📇',
            phone: '📞',
            call: '📱',
        };
        return icons[type] || '📄';
    };

    return (
        <>
            <Button
                variant="outline"
                className="relative h-10 w-full justify-start px-3 shadow-sm transition-all hover:shadow-md md:w-72 md:px-4 md:hover:w-80"
                onClick={() => setOpen(true)}
            >
                <Search className="mr-2 h-4 w-4 shrink-0 text-muted-foreground" />
                <span className="flex-1 truncate text-left text-sm text-muted-foreground">
                    Search menu, customers, campaigns, contacts...
                </span>
                <kbd className="pointer-events-none hidden h-5 select-none items-center gap-0.5 rounded border border-border bg-muted px-1.5 font-mono text-[10px] font-medium text-muted-foreground md:inline-flex">
                    <span className="text-xs">⌘</span>K
                </kbd>
            </Button>

            <CommandDialog open={open} onOpenChange={setOpen}>
                <CommandInput
                    placeholder="Type to search across everything..."
                    value={query}
                    onValueChange={setQuery}
                />
                <CommandList className="max-h-[400px] p-2">
                    {loading && (
                        <div className="flex flex-col items-center justify-center py-12">
                            <div className="mb-3 h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent"></div>
                            <p className="text-sm text-muted-foreground">Searching...</p>
                        </div>
                    )}

                    {!loading && query.length >= 2 && results.length === 0 && (
                        <div className="flex flex-col items-center justify-center py-12">
                            <div className="mb-4 rounded-full bg-muted p-4">
                                <Search className="h-8 w-8 text-muted-foreground" />
                            </div>
                            <p className="mb-1 font-medium">No results found</p>
                            <p className="text-sm text-muted-foreground">
                                Try searching with different keywords
                            </p>
                        </div>
                    )}

                    {!loading && query.length < 2 && (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <div className="mb-4 rounded-full bg-gradient-to-br from-primary/10 to-primary/5 p-4">
                                <Search className="h-8 w-8 text-primary" />
                            </div>
                            <p className="mb-2 font-semibold text-foreground">Quick Search</p>
                            <p className="mb-4 max-w-sm text-sm text-muted-foreground">
                                Search for menu items, customers, agents, campaigns, contacts, phone numbers, call logs and more
                            </p>
                            <div className="flex flex-wrap items-center justify-center gap-2">
                                <kbd className="rounded-md border bg-muted px-2 py-1 text-xs font-medium">
                                    ⌘K
                                </kbd>
                                <span className="text-xs text-muted-foreground">to open</span>
                                <kbd className="rounded-md border bg-muted px-2 py-1 text-xs font-medium">
                                    ↑↓
                                </kbd>
                                <span className="text-xs text-muted-foreground">to navigate</span>
                                <kbd className="rounded-md border bg-muted px-2 py-1 text-xs font-medium">
                                    ↵
                                </kbd>
                                <span className="text-xs text-muted-foreground">to select</span>
                            </div>
                        </div>
                    )}

                    {!loading &&
                        Object.entries(
                            results.reduce(
                                (acc, result) => {
                                    if (!acc[result.type]) acc[result.type] = [];
                                    acc[result.type].push(result);
                                    return acc;
                                },
                                {} as Record<string, SearchResult[]>
                            )
                        ).map(([type, items]) => (
                            <CommandGroup 
                                key={type} 
                                heading={getTypeLabel(type)}
                                className="mb-2"
                            >
                                {items.map((result) => (
                                    <CommandItem
                                        key={`${result.type}-${result.id}`}
                                        value={`${result.type}-${result.id}-${result.title}`}
                                        onSelect={() => handleSelect(result.url)}
                                        className="group rounded-lg px-3 py-2.5 hover:bg-accent/50"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="flex h-9 w-9 items-center justify-center rounded-md bg-primary/10 text-lg transition-colors group-hover:bg-primary/20">
                                                {getTypeIcon(result.type)}
                                            </div>
                                            <div className="flex flex-1 flex-col">
                                                <span className="font-medium">{result.title}</span>
                                                {result.subtitle && (
                                                    <span className="text-xs text-muted-foreground">
                                                        {result.subtitle}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        ))}
                </CommandList>
            </CommandDialog>
        </>
    );
}
