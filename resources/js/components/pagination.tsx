import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { ChevronLeft, ChevronRight } from 'lucide-react';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginationMeta {
    current_page: number;
    from: number | null;
    last_page: number;
    path: string;
    per_page: number;
    to: number | null;
    total: number;
}

interface Props {
    links: PaginationLink[];
    meta: PaginationMeta;
}

export default function Pagination({ links, meta }: Props) {
    // Early return if no valid pagination data
    if (!meta || !links || meta.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3 sm:px-6">
            <div className="flex flex-1 justify-between sm:hidden">
                {links[0]?.url ? (
                    <Link href={links[0].url}>
                        <Button variant="outline" size="sm">
                            Previous
                        </Button>
                    </Link>
                ) : (
                    <Button variant="outline" size="sm" disabled>
                        Previous
                    </Button>
                )}
                {links[links.length - 1]?.url ? (
                    <Link href={links[links.length - 1].url!}>
                        <Button variant="outline" size="sm">
                            Next
                        </Button>
                    </Link>
                ) : (
                    <Button variant="outline" size="sm" disabled>
                        Next
                    </Button>
                )}
            </div>
            <div className="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm text-slate-700">
                        Showing{' '}
                        <span className="font-medium">{meta.from}</span> to{' '}
                        <span className="font-medium">{meta.to}</span> of{' '}
                        <span className="font-medium">{meta.total}</span> results
                    </p>
                </div>
                <div>
                    <nav className="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                        {links.map((link, index) => {
                            if (index === 0) {
                                return (
                                    <Link
                                        key={index}
                                        href={link.url || '#'}
                                        className={`relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 ${
                                            !link.url ? 'cursor-not-allowed opacity-50' : 'hover:bg-slate-50'
                                        }`}
                                    >
                                        <span className="sr-only">Previous</span>
                                        <ChevronLeft className="h-5 w-5" aria-hidden="true" />
                                    </Link>
                                );
                            }

                            if (index === links.length - 1) {
                                return (
                                    <Link
                                        key={index}
                                        href={link.url || '#'}
                                        className={`relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 ${
                                            !link.url ? 'cursor-not-allowed opacity-50' : 'hover:bg-slate-50'
                                        }`}
                                    >
                                        <span className="sr-only">Next</span>
                                        <ChevronRight className="h-5 w-5" aria-hidden="true" />
                                    </Link>
                                );
                            }

                            return (
                                <Link
                                    key={index}
                                    href={link.url || '#'}
                                    className={`relative inline-flex items-center px-4 py-2 text-sm font-semibold ${
                                        link.active
                                            ? 'z-10 bg-blue-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600'
                                            : 'text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50'
                                    }`}
                                >
                                    {link.label
                                        .replace(/&laquo;/g, '\u00AB')
                                        .replace(/&raquo;/g, '\u00BB')
                                        .replace(/&amp;/g, '&')}
                                </Link>
                            );
                        })}
                    </nav>
                </div>
            </div>
        </div>
    );
}
