import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head, Link, router } from '@inertiajs/react';
import { Mail, Phone, User, SquarePen, UserPlus, Trash2, Search } from 'lucide-react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { useState, useMemo, useEffect, useRef } from 'react';

interface Contact {
    id: number;
    name: string;
    email: string;
    phone: string;
    status?: string;
}

interface ContactList {
    id: number;
    name: string;
    description: string;
    contacts_count: number;
    contacts: Contact[];
    created_at: string;
}

interface Props {
    list: ContactList;
    availableContacts: Contact[];
}

export default function Show({ list, availableContacts = [] }: Props) {
  const { t } = useTranslation();
    const listData = list || {
        id: 0,
        name: '',
        description: '',
        contacts_count: 0,
        contacts: [],
        created_at: ''
    };
    
    const contacts = Array.isArray(listData.contacts) ? listData.contacts : [];
    const [selectedContacts, setSelectedContacts] = useState<number[]>([]);
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [contactSearchQuery, setContactSearchQuery] = useState('');
    const [displayedCount, setDisplayedCount] = useState(50); // Start with 50 contacts
    const tableContainerRef = useRef<HTMLDivElement>(null);

    // Filter contacts in the "Add Contacts" dialog based on search query
    const filteredContacts = useMemo(() => {
        if (!searchQuery.trim()) return availableContacts;
        
        const query = searchQuery.toLowerCase();
        return availableContacts.filter(contact =>
            contact.name.toLowerCase().includes(query) ||
            contact.email?.toLowerCase().includes(query) ||
            contact.phone?.toLowerCase().includes(query)
        );
    }, [availableContacts, searchQuery]);

    // Filter contacts in the main table based on search query
    const filteredTableContacts = useMemo(() => {
        if (!contactSearchQuery.trim()) return contacts;
        
        const query = contactSearchQuery.toLowerCase();
        return contacts.filter(contact =>
            contact.name.toLowerCase().includes(query) ||
            contact.email?.toLowerCase().includes(query) ||
            contact.phone?.toLowerCase().includes(query)
        );
    }, [contacts, contactSearchQuery]);

    // Paginated contacts for display (infinite scroll)
    const displayedContacts = useMemo(() => {
        return filteredTableContacts.slice(0, displayedCount);
    }, [filteredTableContacts, displayedCount]);

    // Infinite scroll handler
    useEffect(() => {
        const handleScroll = (e: Event) => {
            const target = e.target as HTMLDivElement;
            if (!target) return;

            const { scrollTop, scrollHeight, clientHeight } = target;
            const scrollPercentage = (scrollTop + clientHeight) / scrollHeight;

            // Load more when 80% scrolled
            if (scrollPercentage > 0.8 && displayedCount < filteredTableContacts.length) {
                setDisplayedCount(prev => Math.min(prev + 50, filteredTableContacts.length));
            }
        };

        const container = tableContainerRef.current;
        if (container) {
            container.addEventListener('scroll', handleScroll);
            return () => container.removeEventListener('scroll', handleScroll);
        }
    }, [displayedCount, filteredTableContacts.length]);

    // Reset displayed count when search changes
    useEffect(() => {
        setDisplayedCount(50);
    }, [contactSearchQuery]);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('common.dashboard'), href: dashboard().url,
        },
        {
            title: t('contact_lists.title'), href: '/contact-lists',
        },
        {
            title: listData.name,
            href: `/contact-lists/${listData.id}`,
        },
    ];

    const handleToggleContact = (contactId: number) => {
        setSelectedContacts(prev =>
            prev.includes(contactId)
                ? prev.filter(id => id !== contactId)
                : [...prev, contactId]
        );
    };

    const handleSelectAll = () => {
        if (selectedContacts.length === filteredContacts.length) {
            // Deselect all
            setSelectedContacts([]);
        } else {
            // Select all filtered contacts
            setSelectedContacts(filteredContacts.map(c => c.id));
        }
    };

    const handleAddContacts = () => {
        if (selectedContacts.length === 0) return;

        router.post(`/contact-lists/${listData.id}/add`, {
            contact_ids: selectedContacts,
        }, {
            onSuccess: () => {
                setSelectedContacts([]);
                setSearchQuery('');
                setIsDialogOpen(false);
            },
        });
    };

    const handleRemoveContact = (contactId: number) => {
        if (confirm('Are you sure you want to remove this contact from the list?')) {
            router.post(`/contact-lists/${listData.id}/remove`, {
                contact_ids: [contactId],
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={listData.name} />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={listData.name}
                        description={listData.description || 'Contact list details'}
                    />
                    <div className="flex gap-2">
                        <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                            <DialogTrigger asChild>
                                <Button variant="default">
                                    <UserPlus className="mr-2 h-4 w-4" />
                                    Add Contacts
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="max-w-3xl max-h-[85vh] flex flex-col">
                                <DialogHeader>
                                    <DialogTitle>Add Contacts to List</DialogTitle>
                                    <DialogDescription>
                                        Select contacts to add to "{listData.name}"
                                    </DialogDescription>
                                </DialogHeader>
                                
                                <div className="flex-1 overflow-hidden flex flex-col space-y-4">
                                    {availableContacts.length === 0 ? (
                                        <p className="text-center text-muted-foreground py-8">
                                            All your contacts are already in this list or you have no contacts.
                                        </p>
                                    ) : (
                                        <>
                                            {/* Search Bar */}
                                            <div className="relative">
                                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                                <Input
                                                    placeholder="Search by name, email, or phone..."
                                                    value={searchQuery}
                                                    onChange={(e) => setSearchQuery(e.target.value)}
                                                    className="pl-10"
                                                />
                                            </div>

                                            {/* Select All */}
                                            <div className="flex items-center justify-between py-2 px-1 border-b">
                                                <div className="flex items-center space-x-3">
                                                    <Checkbox
                                                        checked={filteredContacts.length > 0 && selectedContacts.length === filteredContacts.length}
                                                        onCheckedChange={handleSelectAll}
                                                    />
                                                    <span className="text-sm font-medium">
                                                        Select All {filteredContacts.length > 0 && `(${filteredContacts.length})`}
                                                    </span>
                                                </div>
                                                {selectedContacts.length > 0 && (
                                                    <span className="text-sm text-muted-foreground">
                                                        {selectedContacts.length} selected
                                                    </span>
                                                )}
                                            </div>

                                            {/* Contacts List - Scrollable */}
                                            <div className="flex-1 overflow-y-auto pr-2 space-y-2">
                                                {filteredContacts.length === 0 ? (
                                                    <p className="text-center text-muted-foreground py-8">
                                                        No contacts found matching "{searchQuery}"
                                                    </p>
                                                ) : (
                                                    filteredContacts.map((contact) => (
                                                        <div
                                                            key={contact.id}
                                                            className="flex items-center space-x-3 p-3 border rounded-lg hover:bg-accent cursor-pointer"
                                                            onClick={() => handleToggleContact(contact.id)}
                                                        >
                                                            <Checkbox
                                                                checked={selectedContacts.includes(contact.id)}
                                                                onCheckedChange={() => handleToggleContact(contact.id)}
                                                                onClick={(e) => e.stopPropagation()}
                                                            />
                                                            <div className="flex-1 min-w-0">
                                                                <div className="font-medium truncate">{contact.name}</div>
                                                                <div className="text-sm text-muted-foreground truncate">
                                                                    {contact.email} • {contact.phone}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ))
                                                )}
                                            </div>
                                            
                                            {/* Actions */}
                                            <div className="flex justify-between items-center pt-4 border-t">
                                                <span className="text-sm text-muted-foreground">
                                                    {selectedContacts.length} of {filteredContacts.length} contact{filteredContacts.length !== 1 ? 's' : ''} selected
                                                </span>
                                                <div className="flex gap-2">
                                                    <Button
                                                        variant="outline"
                                                        onClick={() => {
                                                            setIsDialogOpen(false);
                                                            setSelectedContacts([]);
                                                            setSearchQuery('');
                                                        }}
                                                    >
                                                        Cancel
                                                    </Button>
                                                    <Button
                                                        onClick={handleAddContacts}
                                                        disabled={selectedContacts.length === 0}
                                                    >
                                                        Add {selectedContacts.length > 0 && `(${selectedContacts.length})`}
                                                    </Button>
                                                </div>
                                            </div>
                                        </>
                                    )}
                                </div>
                            </DialogContent>
                        </Dialog>
                        
                        <Button variant="outline" asChild>
                            <Link href={`/contact-lists/${listData.id}/edit`}>
                                <SquarePen className="mr-2 h-4 w-4" />
                                Edit List
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium text-muted-foreground">{t('contact_lists.total_contacts')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {Number(listData.contacts_count || 0)}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium text-muted-foreground">{t('contact_lists.created_date')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-lg">
                                {new Date(listData.created_at).toLocaleDateString()}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle>{t('contact_lists.contacts')}</CardTitle>
                            {contacts.length > 0 && (
                                <div className="flex items-center gap-2">
                                    <div className="relative">
                                        <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            placeholder="Search contacts..."
                                            value={contactSearchQuery}
                                            onChange={(e) => setContactSearchQuery(e.target.value)}
                                            className="pl-8 w-[250px]"
                                        />
                                    </div>
                                </div>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        {contacts.length === 0 ? (
                            <div className="text-center py-12">
                                <p className="text-muted-foreground mb-4">
                                    No contacts in this list yet
                                </p>
                                <Button onClick={() => setIsDialogOpen(true)}>
                                    <UserPlus className="mr-2 h-4 w-4" />
                                    Add Contacts
                                </Button>
                            </div>
                        ) : filteredTableContacts.length === 0 ? (
                            <div className="text-center py-12">
                                <p className="text-muted-foreground mb-4">
                                    No contacts found matching "{contactSearchQuery}"
                                </p>
                                <Button
                                    variant="outline"
                                    onClick={() => setContactSearchQuery('')}
                                >
                                    Clear Search
                                </Button>
                            </div>
                        ) : (
                            <div ref={tableContainerRef} className="max-h-[600px] overflow-y-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Name</TableHead>
                                            <TableHead>Email</TableHead>
                                            <TableHead>Phone</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {displayedContacts.map((contact) => (
                                            <TableRow key={contact.id}>
                                                <TableCell className="font-medium">
                                                    <div className="flex items-center gap-2">
                                                        <User className="h-4 w-4 text-muted-foreground" />
                                                        {contact.name}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <Mail className="h-4 w-4 text-muted-foreground" />
                                                        {contact.email}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <Phone className="h-4 w-4 text-muted-foreground" />
                                                        {contact.phone}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {contact.status && (
                                                        <Badge variant={contact.status === 'active' ? 'default' : 'secondary'}>
                                                            {contact.status}
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleRemoveContact(contact.id)}
                                                    >
                                                        <Trash2 className="h-4 w-4 text-destructive" />
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                                {displayedCount < filteredTableContacts.length && (
                                    <div className="text-center py-4 text-sm text-muted-foreground">
                                        Showing {displayedCount} of {filteredTableContacts.length} contacts. Scroll for more...
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
