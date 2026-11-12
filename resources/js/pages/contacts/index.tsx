import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card } from '@/components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Search, Plus, Upload, CheckCircle, Phone, PhoneCall } from 'lucide-react';
import { useState } from 'react';
import { PageHelp } from '@/components/page-help';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import Heading from '@/components/heading';
import { ImportModal } from '@/components/contacts/import-modal';
import { PhoneValidationModal } from '@/components/contacts/phone-validation-modal';
import { useTranslation } from 'react-i18next';
import { useSoftphone } from '@/contexts/SoftphoneContext';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { formatPhone } from '@/lib/phone';

interface Contact {
  id: number;
  first_name: string;
  last_name: string;
  phone_number: string;
  email?: string;
  company?: string;
  website?: string;
  tags?: string[];
  lists?: ContactList[];
  status: string;
  last_contacted_at?: string;
  total_calls?: number;
}

interface ContactTag {
  id: number;
  name: string;
}

interface ContactList {
  id: number;
  name: string;
}

interface Props {
  contacts: {
    data: Contact[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  tags?: ContactTag[];
  lists?: ContactList[];
  filters?: {
    search?: string;
    list_id?: string;
  };
}

export default function ContactsIndex({ contacts, tags = [], lists = [], filters = {} }: Props) {
  const { t } = useTranslation();
  const { callContact, deviceState } = useSoftphone();
  const [search, setSearch] = useState(filters.search || '');
  const [selectedListId, setSelectedListId] = useState(filters.list_id || '');
  const [importModalOpen, setImportModalOpen] = useState(false);
  const [validationModalOpen, setValidationModalOpen] = useState(false);

  const handleSearch = () => {
    router.get('/contacts', { search, list_id: selectedListId || undefined }, { preserveState: true });
  };

  const handleListFilter = (listId: string) => {
    setSelectedListId(listId);
    router.get('/contacts', { search, list_id: listId === 'all' ? undefined : listId }, { preserveState: true });
  };

  const handleCallContact = (contact: Contact) => {
    const name = `${contact.first_name || ''} ${contact.last_name || ''}`.trim();
    callContact(contact.phone_number, contact.id, name);
  };

  const handleStartCallingSession = () => {
    const params = selectedListId && selectedListId !== 'all' ? `?list_id=${selectedListId}` : '';
    router.get(`/contacts/calling-session${params}`);
  };

  const canMakeCalls = deviceState === 'registered';

  const breadcrumbs: BreadcrumbItem[] = [
    {
      title: 'Dashboard',
      href: dashboard().url,
    },
    {
      title: t('contacts.title'),
      href: '/contacts',
    },
  ];

  const helpSections = [
    {
      title: t('contacts.management_overview'),
      content: t('contacts.management_description'),
    },
    {
      title: t('contacts.adding_help'),
      content: t('contacts.adding_help_description'),
    },
    {
      title: t('contacts.info_help'),
      content: t('contacts.info_help_description'),
    },
    {
      title: t('contacts.status_help'),
      content: t('contacts.status_help_description'),
    },
    {
      title: t('contacts.search_help'),
      content: t('contacts.search_help_description'),
    },
    {
      title: t('contacts.bulk_help'),
      content: t('contacts.bulk_help_description'),
    },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('contacts.title')} />

      <div className="mx-auto max-w-7xl space-y-6">
        <div className="flex justify-between items-center">
          <Heading title={t('contacts.title')} description={t('contacts.description')} />
          <div className="flex gap-2">
            <PageHelp title="Contacts Help" sections={helpSections} />
            <Button variant="outline" onClick={handleStartCallingSession}>
              <PhoneCall className="mr-2 h-4 w-4" />
              {t('contacts.start_calling')}
            </Button>
            <Button variant="outline" onClick={() => setValidationModalOpen(true)}>
              <CheckCircle className="mr-2 h-4 w-4" />
              {t('contacts.check_valid_numbers')}
            </Button>
            <Button variant="outline" onClick={() => setImportModalOpen(true)}>
              <Upload className="mr-2 h-4 w-4" />
              {t('contacts.import')}
            </Button>
            <Button onClick={() => router.get('/contacts/create')}>
              <Plus className="mr-2 h-4 w-4" />
              {t('contacts.add')}
            </Button>
          </div>
        </div>

        <Card className="p-6">
          <div className="flex gap-4 mb-6">
            <div className="flex-1">
              <div className="relative">
                <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder={t('contacts.search')}
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                  className="pl-10"
                />
              </div>
            </div>
            <Button onClick={handleSearch}>{t('common.search')}</Button>
          </div>

          {lists.length > 0 && (
            <div className="flex flex-wrap gap-2 mb-4">
              <button
                onClick={() => handleListFilter('all')}
                className={`px-3 py-1 rounded-full text-sm font-medium transition-colors ${
                  !selectedListId || selectedListId === 'all'
                    ? 'bg-primary text-primary-foreground'
                    : 'bg-muted hover:bg-muted/80'
                }`}
              >
                All Contacts ({contacts.total})
              </button>
              {lists.map((list) => (
                <button
                  key={list.id}
                  onClick={() => handleListFilter(String(list.id))}
                  className={`px-3 py-1 rounded-full text-sm font-medium transition-colors ${
                    selectedListId === String(list.id)
                      ? 'bg-primary text-primary-foreground'
                      : 'bg-muted hover:bg-muted/80'
                  }`}
                >
                  {list.name}
                </button>
              ))}
              <button
                onClick={() => handleListFilter('unassigned')}
                className={`px-3 py-1 rounded-full text-sm font-medium transition-colors ${
                  selectedListId === 'unassigned'
                    ? 'bg-primary text-primary-foreground'
                    : 'bg-muted hover:bg-muted/80'
                }`}
              >
                Not in any list
              </button>
            </div>
          )}

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('common.name')}</TableHead>
                <TableHead>{t('common.phone')}</TableHead>
                <TableHead>{t('common.email')}</TableHead>
                <TableHead>{t('common.company')}</TableHead>
                <TableHead>Website</TableHead>
                <TableHead>Lists</TableHead>
                <TableHead>{t('common.status')}</TableHead>
                <TableHead className="text-right">{t('common.actions')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {contacts?.data?.length > 0 ? (
                contacts.data.map((contact) => (
                  <TableRow key={contact.id}>
                    <TableCell className="font-medium">
                      {contact.first_name} {contact.last_name}
                    </TableCell>
                    <TableCell>{formatPhone(contact.phone_number)}</TableCell>
                    <TableCell>{contact.email || '-'}</TableCell>
                    <TableCell>{contact.company || '-'}</TableCell>
                    <TableCell>
                      {contact.website ? (
                        <a href={contact.website} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:underline text-sm truncate max-w-[120px] block">
                          {contact.website.replace(/^https?:\/\/(www\.)?/, '')}
                        </a>
                      ) : '-'}
                    </TableCell>
                    <TableCell>
                      {contact.lists && contact.lists.length > 0
                        ? contact.lists.map(l => l.name).join(', ')
                        : <span className="text-muted-foreground text-sm">—</span>}
                    </TableCell>
                    <TableCell>
                      <Badge variant={contact.status === 'active' ? 'default' : 'secondary'}>
                        {contact.status}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex items-center justify-end gap-1">
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleCallContact(contact)}
                          disabled={!canMakeCalls}
                          title={canMakeCalls ? t('contacts.call_contact') : t('contacts.phone_not_ready')}
                          className="text-green-600 hover:text-green-700 hover:bg-green-50"
                        >
                          <Phone className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => router.get(`/contacts/${contact.id}`)}
                        >
                          {t('common.view')}
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))
              ) : (
                <TableRow>
                  <TableCell colSpan={6} className="text-center py-8">
                    {t('contacts.no_contacts')}
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>

          {contacts?.total > contacts?.per_page && (
            <div className="mt-4 flex justify-center gap-2">
              {(() => {
                const current = contacts.current_page;
                const last = contacts.last_page;
                const pages: (number | string)[] = [];

                // Always show first page
                pages.push(1);

                // Show ellipsis or pages near current
                if (current > 3) pages.push('...');
                for (let i = Math.max(2, current - 1); i <= Math.min(last - 1, current + 1); i++) {
                  pages.push(i);
                }
                if (current < last - 2) pages.push('...');

                // Always show last page (if > 1)
                if (last > 1) pages.push(last);

                return pages.map((page, idx) =>
                  page === '...' ? (
                    <span key={`ellipsis-${idx}`} className="px-2 py-1 text-muted-foreground">...</span>
                  ) : (
                    <Button
                      key={page}
                      variant={page === current ? 'default' : 'outline'}
                      size="sm"
                      onClick={() => router.get('/contacts', { page, search })}
                    >
                      {page}
                    </Button>
                  )
                );
              })()}
            </div>
          )}
        </Card>
      </div>

      <ImportModal
        open={importModalOpen}
        onClose={() => setImportModalOpen(false)}
        tags={tags}
        lists={lists}
      />

      <PhoneValidationModal
        open={validationModalOpen}
        onClose={() => setValidationModalOpen(false)}
        onValidate={() => {
          // Optionally refresh contacts list after validation
          // router.reload({ only: ['contacts'] });
        }}
      />
    </AppLayout>
  );
}
