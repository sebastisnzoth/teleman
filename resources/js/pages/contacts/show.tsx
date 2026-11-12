import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Edit, Phone, Mail, Building, Globe, Facebook, Instagram, Linkedin, Twitter, ExternalLink } from 'lucide-react';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { PageHelp } from '@/components/page-help';
import { ContactQualityChecker } from '@/components/contacts/contact-quality-checker';
import { BreadcrumbItem } from '@/types';
import { useSoftphone } from '@/contexts/SoftphoneContext';

interface Contact {
  id: number;
  first_name: string;
  last_name: string;
  phone_number: string;
  email?: string;
  company?: string;
  website?: string;
  facebook_url?: string;
  instagram_url?: string;
  linkedin_url?: string;
  twitter_url?: string;
  notes?: string;
  status: string;
  created_at: string;
}

interface Call {
  id: number;
  direction: string;
  status: string;
  duration: number;
  disposition?: string;
  call_notes?: string;
  created_at: string;
}

interface Props {
  contact: Contact;
  recentCalls?: Call[];
}

const dispositionColors: Record<string, string> = {
  interested: 'bg-green-100 text-green-800',
  not_interested: 'bg-red-100 text-red-800',
  callback: 'bg-blue-100 text-blue-800',
  wrong_number: 'bg-orange-100 text-orange-800',
  voicemail: 'bg-purple-100 text-purple-800',
  no_answer: 'bg-gray-100 text-gray-800',
  do_not_call: 'bg-red-200 text-red-900',
};

export default function ContactShow({ contact, recentCalls = [] }: Props) {
  const { t } = useTranslation();
  const { callContact, deviceState } = useSoftphone();
  const canMakeCalls = deviceState === 'registered';

  const handleCallContact = () => {
    const name = `${contact.first_name || ''} ${contact.last_name || ''}`.trim();
    callContact(contact.phone_number, contact.id, name);
  };

  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: '/dashboard' },
    { title: t('contacts.title'), href: '/contacts' },
    { title: `${contact.first_name} ${contact.last_name}`, href: `/contacts/${contact.id}` },
  ];

  const helpSections = [
    {
      title: 'Contact Details Overview',
      content: 'This page displays comprehensive information about a specific contact, including their personal details, contact information, and call history.',
    },
    {
      title: 'Contact Information',
      content: 'View the contact\'s phone number, email address, and company name. You can quickly identify the best way to reach out to this contact.',
    },
    {
      title: 'Recent Calls',
      content: 'See a list of recent calls with this contact, including the date & time, direction (inbound/outbound), status, and duration of each call.',
    },
    {
      title: 'Editing Contact',
      content: 'Click the "Edit" button in the top-right corner to modify the contact\'s information, update their details, or add notes.',
    },
    {
      title: 'Call History',
      content: 'Click "View" on any call in the Recent Calls table to see detailed information about that specific call, including recordings and transcripts if available.',
    },
    {
      title: 'Navigation',
      content: 'Use the "Back" button to return to the contacts list, or use the breadcrumb navigation at the top of the page to navigate to other sections.',
    },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={`${contact.first_name} ${contact.last_name}`} />

      <div className="mx-auto max-w-7xl space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-3xl font-bold">{contact.first_name} {contact.last_name}</h1>
            <p className="text-muted-foreground">Contact details</p>
          </div>
          <div className="flex gap-2">
            <PageHelp title={t('contacts.contact_details_help')} sections={helpSections} />
            <Button
              variant="default"
              onClick={handleCallContact}
              disabled={!canMakeCalls}
              className="bg-green-600 hover:bg-green-700"
            >
              <Phone className="mr-2 h-4 w-4" />
              {t('contacts.call_contact')}
            </Button>
            <Button variant="outline" onClick={() => router.get('/contacts')}>
              Back
            </Button>
            <Button variant="outline" onClick={() => router.get(`/contacts/${contact.id}/edit`)}>
              <Edit className="mr-2 h-4 w-4" />
              Edit
            </Button>
          </div>
        </div>

        <Card className="p-6">
          <div className="grid gap-6 md:grid-cols-3">
            <div className="flex items-center gap-4">
              <div className="p-3 bg-primary/10 rounded-lg">
                <Phone className="h-6 w-6 text-primary" />
              </div>
              <div>
                <p className="text-sm text-muted-foreground">Phone</p>
                <p className="font-medium">{contact.phone_number}</p>
              </div>
            </div>

            <div className="flex items-center gap-4">
              <div className="p-3 bg-blue-500/10 rounded-lg">
                <Mail className="h-6 w-6 text-blue-600" />
              </div>
              <div>
                <p className="text-sm text-muted-foreground">Email</p>
                <p className="font-medium">{contact.email || 'N/A'}</p>
              </div>
            </div>

            <div className="flex items-center gap-4">
              <div className="p-3 bg-green-500/10 rounded-lg">
                <Building className="h-6 w-6 text-green-600" />
              </div>
              <div>
                <p className="text-sm text-muted-foreground">{t('common.company')}</p>
                <p className="font-medium">{contact.company || 'N/A'}</p>
              </div>
            </div>
          </div>

          {/* Website */}
          {contact.website && (
            <div className="mt-6 flex items-center gap-4">
              <div className="p-3 bg-violet-500/10 rounded-lg">
                <Globe className="h-6 w-6 text-violet-600" />
              </div>
              <div>
                <p className="text-sm text-muted-foreground">{t('contacts.website')}</p>
                <a href={contact.website} target="_blank" rel="noopener noreferrer" className="font-medium text-primary hover:underline inline-flex items-center gap-1">
                  {contact.website}
                  <ExternalLink className="h-3 w-3" />
                </a>
              </div>
            </div>
          )}

          {/* Social Media Links */}
          {(contact.facebook_url || contact.instagram_url || contact.linkedin_url || contact.twitter_url) && (
            <div className="mt-6">
              <h3 className="font-semibold mb-3">{t('contacts.social_media_links')}</h3>
              <div className="flex flex-wrap gap-3">
                {contact.facebook_url && (
                  <a href={contact.facebook_url} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-500/10 text-blue-700 hover:bg-blue-500/20 transition-colors">
                    <Facebook className="h-4 w-4" />
                    <span className="text-sm font-medium">Facebook</span>
                    <ExternalLink className="h-3 w-3" />
                  </a>
                )}
                {contact.instagram_url && (
                  <a href={contact.instagram_url} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-pink-500/10 text-pink-700 hover:bg-pink-500/20 transition-colors">
                    <Instagram className="h-4 w-4" />
                    <span className="text-sm font-medium">Instagram</span>
                    <ExternalLink className="h-3 w-3" />
                  </a>
                )}
                {contact.linkedin_url && (
                  <a href={contact.linkedin_url} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-sky-500/10 text-sky-700 hover:bg-sky-500/20 transition-colors">
                    <Linkedin className="h-4 w-4" />
                    <span className="text-sm font-medium">LinkedIn</span>
                    <ExternalLink className="h-3 w-3" />
                  </a>
                )}
                {contact.twitter_url && (
                  <a href={contact.twitter_url} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-neutral-500/10 text-neutral-700 hover:bg-neutral-500/20 transition-colors">
                    <Twitter className="h-4 w-4" />
                    <span className="text-sm font-medium">X (Twitter)</span>
                    <ExternalLink className="h-3 w-3" />
                  </a>
                )}
              </div>
            </div>
          )}

          {contact.notes && (
            <div className="mt-6">
              <h3 className="font-semibold mb-2">{t('contacts.notes')}</h3>
              <p className="text-muted-foreground whitespace-pre-line">{contact.notes}</p>
            </div>
          )}
        </Card>

        {/* AI Data Quality Check */}
        <Card className="p-6">
          <h2 className="text-xl font-semibold mb-4">AI Data Quality Check</h2>
          <p className="text-sm text-muted-foreground mb-4">
            Use AI to analyze and improve the quality of this contact's data. Get suggestions for formatting, missing fields, and potential duplicates.
          </p>
          <ContactQualityChecker
            contactId={contact.id}
            contactData={{
              phone_number: contact.phone_number,
              first_name: contact.first_name,
              last_name: contact.last_name,
              email: contact.email,
              company: contact.company,
            }}
            onApply={() => router.reload({ only: ['contact'] })}
          />
        </Card>

        <Card className="p-6">
          <h2 className="text-xl font-semibold mb-4">Recent Calls</h2>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Date & Time</TableHead>
                <TableHead>Direction</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Disposition</TableHead>
                <TableHead>Duration</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {recentCalls.length > 0 ? (
                recentCalls.map((call) => (
                  <TableRow key={call.id}>
                    <TableCell>{new Date(call.created_at).toLocaleString()}</TableCell>
                    <TableCell>
                      <Badge variant={call.direction === 'outbound' ? 'default' : 'secondary'}>
                        {call.direction}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <Badge variant={call.status === 'completed' ? 'default' : 'secondary'}>
                        {call.status}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      {call.disposition ? (
                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${dispositionColors[call.disposition] || 'bg-gray-100 text-gray-800'}`}>
                          {t(`calls.disposition_${call.disposition}`)}
                        </span>
                      ) : (
                        <span className="text-muted-foreground text-xs">-</span>
                      )}
                    </TableCell>
                    <TableCell>{call.duration}s</TableCell>
                    <TableCell className="text-right">
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => router.get(`/calls/${call.id}`)}
                      >
                        View
                      </Button>
                    </TableCell>
                  </TableRow>
                ))
              ) : (
                <TableRow>
                  <TableCell colSpan={6} className="text-center py-8">
                    No calls yet
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </Card>
      </div>
    </AppLayout>
  );
}
