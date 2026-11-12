import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Phone, SkipForward, ChevronLeft, ChevronRight, User, Clock, PhoneCall } from 'lucide-react';
import { useState, useEffect, useCallback } from 'react';
import { useSoftphone } from '@/contexts/SoftphoneContext';
import { BreadcrumbItem } from '@/types';
import { formatPhone } from '@/lib/phone';

interface Contact {
  id: number;
  first_name: string;
  last_name: string;
  phone_number: string;
  email?: string;
  company?: string;
  website?: string;
  notes?: string;
  last_contacted_at?: string;
  total_calls?: number;
}

interface RecentCall {
  id: number;
  contact_id: number;
  disposition: string;
  call_notes?: string;
  callback_at?: string;
  created_at: string;
}

interface ContactList {
  id: number;
  name: string;
}

interface Props {
  contacts: Contact[];
  recentCalls: Record<string, RecentCall[]>;
  lists: ContactList[];
  selectedListId: number | null;
  selectedListName: string | null;
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

export default function CallingSession({
  contacts,
  recentCalls,
  lists,
  selectedListId,
  selectedListName,
}: Props) {
  const { t } = useTranslation();
  const { callContact, callState, deviceState, activeContactId, setOnDispositionSavedCallback } = useSoftphone();
  const [currentIndex, setCurrentIndex] = useState(0);

  const currentContact = contacts[currentIndex] || null;
  const totalContacts = contacts.length;
  const canMakeCalls = deviceState === 'registered';
  const isCallActive = ['dialing', 'ringing', 'connected'].includes(callState);

  // Get the last disposition for the current contact
  const getLastDisposition = useCallback(
    (contactId: number) => {
      const calls = recentCalls[String(contactId)];
      return calls?.[0] || null;
    },
    [recentCalls]
  );

  const handleCallCurrent = () => {
    if (!currentContact) return;
    const name = `${currentContact.first_name || ''} ${currentContact.last_name || ''}`.trim();
    callContact(currentContact.phone_number, currentContact.id, name);
  };

  const handleNext = () => {
    if (currentIndex < totalContacts - 1) {
      setCurrentIndex(currentIndex + 1);
    }
  };

  const handlePrev = () => {
    if (currentIndex > 0) {
      setCurrentIndex(currentIndex - 1);
    }
  };

  const handleSkip = () => {
    handleNext();
  };

  const handleListChange = (listId: string) => {
    router.get('/contacts/calling-session', {
      list_id: listId === 'all' ? undefined : listId,
    });
  };

  // Register auto-advance callback for disposition modal
  useEffect(() => {
    const advanceToNext = () => {
      setCurrentIndex((prev) => {
        if (prev < totalContacts - 1) return prev + 1;
        return prev;
      });
    };
    setOnDispositionSavedCallback(() => advanceToNext);
    return () => setOnDispositionSavedCallback(null);
  }, [totalContacts, setOnDispositionSavedCallback]);

  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: '/dashboard' },
    { title: t('contacts.title'), href: '/contacts' },
    { title: t('contacts.calling_session'), href: '/contacts/calling-session' },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('contacts.calling_session')} />

      <div className="mx-auto max-w-5xl space-y-6">
        {/* Header */}
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold flex items-center gap-2">
              <PhoneCall className="h-6 w-6" />
              {t('contacts.calling_session')}
            </h1>
            <p className="text-muted-foreground">
              {selectedListName
                ? t('contacts.calling_list', { name: selectedListName })
                : t('contacts.calling_all')}
              {' '} - {currentIndex + 1} / {totalContacts}
            </p>
          </div>
          <div className="flex gap-2 items-center">
            <Select
              value={selectedListId ? String(selectedListId) : 'all'}
              onValueChange={handleListChange}
            >
              <SelectTrigger className="w-[200px]">
                <SelectValue placeholder={t('contacts.select_list')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t('contacts.all_contacts')}</SelectItem>
                {lists.map((list) => (
                  <SelectItem key={list.id} value={String(list.id)}>
                    {list.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Button variant="outline" onClick={() => router.get('/contacts')}>
              {t('contacts.back_to_contacts')}
            </Button>
          </div>
        </div>

        {totalContacts === 0 ? (
          <Card className="p-12 text-center">
            <p className="text-lg text-muted-foreground">{t('contacts.no_contacts_to_call')}</p>
            <Button className="mt-4" onClick={() => router.get('/contacts')}>
              {t('contacts.back_to_contacts')}
            </Button>
          </Card>
        ) : (
          <>
            {/* Progress bar */}
            <div className="w-full bg-muted rounded-full h-2">
              <div
                className="bg-primary h-2 rounded-full transition-all duration-300"
                style={{ width: `${((currentIndex + 1) / totalContacts) * 100}%` }}
              />
            </div>

            {/* Current contact card */}
            {currentContact && (
              <Card className="p-6">
                <div className="flex justify-between items-start">
                  <div className="space-y-4 flex-1">
                    <div className="flex items-center gap-3">
                      <div className="h-12 w-12 bg-primary/10 rounded-full flex items-center justify-center">
                        <User className="h-6 w-6 text-primary" />
                      </div>
                      <div>
                        <h2 className="text-xl font-bold">
                          {currentContact.first_name} {currentContact.last_name}
                        </h2>
                        <p className="text-lg text-muted-foreground">{formatPhone(currentContact.phone_number)}</p>
                      </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                      {currentContact.email && (
                        <div>
                          <span className="text-sm text-muted-foreground">{t('common.email')}</span>
                          <p className="font-medium">{currentContact.email}</p>
                        </div>
                      )}
                      {currentContact.company && (
                        <div>
                          <span className="text-sm text-muted-foreground">{t('common.company')}</span>
                          <p className="font-medium">{currentContact.company}</p>
                        </div>
                      )}
                      {currentContact.website && (
                        <div>
                          <span className="text-sm text-muted-foreground">Website</span>
                          <p className="font-medium">
                            <a href={currentContact.website} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:underline truncate block">
                              {currentContact.website.replace(/^https?:\/\/(www\.)?/, '')}
                            </a>
                          </p>
                        </div>
                      )}
                      <div>
                        <span className="text-sm text-muted-foreground">{t('contacts.total_calls')}</span>
                        <p className="font-medium">{currentContact.total_calls || 0}</p>
                      </div>
                      <div>
                        <span className="text-sm text-muted-foreground">{t('contacts.last_contacted')}</span>
                        <p className="font-medium">
                          {currentContact.last_contacted_at
                            ? new Date(currentContact.last_contacted_at).toLocaleDateString()
                            : t('contacts.never')}
                        </p>
                      </div>
                    </div>

                    {/* Last disposition */}
                    {(() => {
                      const lastCall = getLastDisposition(currentContact.id);
                      if (!lastCall) return null;
                      return (
                        <div className="border rounded-lg p-3 bg-muted/30">
                          <div className="flex items-center gap-2 mb-1">
                            <Clock className="h-4 w-4 text-muted-foreground" />
                            <span className="text-sm text-muted-foreground">
                              {t('contacts.last_call_result')} - {new Date(lastCall.created_at).toLocaleString()}
                            </span>
                          </div>
                          <div className="flex items-center gap-2">
                            <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${dispositionColors[lastCall.disposition] || 'bg-gray-100 text-gray-800'}`}>
                              {t(`calls.disposition_${lastCall.disposition}`)}
                            </span>
                            {lastCall.call_notes && (
                              <span className="text-sm text-muted-foreground truncate">
                                {lastCall.call_notes}
                              </span>
                            )}
                          </div>
                        </div>
                      );
                    })()}

                    {currentContact.notes && (
                      <div>
                        <span className="text-sm text-muted-foreground">{t('common.notes')}</span>
                        <p className="text-sm whitespace-pre-line mt-1">{currentContact.notes}</p>
                      </div>
                    )}
                  </div>
                </div>

                {/* Action buttons */}
                <div className="flex items-center justify-between mt-6 pt-4 border-t">
                  <Button
                    variant="outline"
                    onClick={handlePrev}
                    disabled={currentIndex === 0}
                  >
                    <ChevronLeft className="mr-1 h-4 w-4" />
                    {t('common.previous')}
                  </Button>

                  <div className="flex gap-2">
                    <Button
                      variant="outline"
                      onClick={handleSkip}
                      disabled={currentIndex >= totalContacts - 1}
                    >
                      <SkipForward className="mr-1 h-4 w-4" />
                      {t('contacts.skip_contact')}
                    </Button>
                    <Button
                      size="lg"
                      onClick={handleCallCurrent}
                      disabled={!canMakeCalls || isCallActive}
                      className="bg-green-600 hover:bg-green-700 px-8"
                    >
                      <Phone className="mr-2 h-5 w-5" />
                      {isCallActive ? t('contacts.call_in_progress') : t('contacts.call_now')}
                    </Button>
                  </div>

                  <Button
                    variant="outline"
                    onClick={handleNext}
                    disabled={currentIndex >= totalContacts - 1}
                  >
                    {t('common.next')}
                    <ChevronRight className="ml-1 h-4 w-4" />
                  </Button>
                </div>
              </Card>
            )}

            {/* Contact list sidebar */}
            <Card className="p-4">
              <h3 className="font-semibold mb-3">{t('contacts.contact_queue')} ({totalContacts})</h3>
              <div className="max-h-[300px] overflow-y-auto space-y-1">
                {contacts.map((contact, idx) => {
                  const lastCall = getLastDisposition(contact.id);
                  return (
                    <button
                      key={contact.id}
                      onClick={() => setCurrentIndex(idx)}
                      className={`w-full text-left px-3 py-2 rounded-md text-sm flex items-center justify-between transition-colors ${
                        idx === currentIndex
                          ? 'bg-primary text-primary-foreground'
                          : 'hover:bg-muted'
                      }`}
                    >
                      <span className="truncate">
                        {idx + 1}. {contact.first_name} {contact.last_name}
                        <span className={`ml-2 ${idx === currentIndex ? 'text-primary-foreground/70' : 'text-muted-foreground'}`}>
                          {formatPhone(contact.phone_number)}
                        </span>
                      </span>
                      {lastCall && (
                        <span className={`ml-2 shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium ${
                          idx === currentIndex ? 'bg-primary-foreground/20 text-primary-foreground' : dispositionColors[lastCall.disposition] || 'bg-gray-100 text-gray-800'
                        }`}>
                          {t(`calls.disposition_${lastCall.disposition}`)}
                        </span>
                      )}
                    </button>
                  );
                })}
              </div>
            </Card>
          </>
        )}
      </div>

    </AppLayout>
  );
}
