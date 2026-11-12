import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Send, Loader2, Users, User, List, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import PhoneNumberTagInput from '@/components/sms/phone-number-tag-input';
import ValidationModal from '@/components/sms/validation-modal';
import SendResultsModal from '@/components/sms/send-results-modal';
import { validatePhoneNumber } from '@/lib/phone-validation';
import { toast } from 'sonner';
import axios from 'axios';
import { Checkbox } from '@/components/ui/checkbox';

interface PhoneTag {
  phone: string;
  isValid: boolean;
  error?: string;
}

interface PhoneNumber {
  id: number;
  number: string;
  friendly_name: string | null;
}

interface ContactList {
  id: number;
  name: string;
  description: string | null;
  contacts_count: number;
}

interface Props {
  phoneNumbers: PhoneNumber[];
  contactLists: ContactList[];
}

export default function ComposeSms({ phoneNumbers, contactLists }: Props) {
  const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
  {
    title: t('nav.home'),
    href: dashboard().url,
  },
  {
    title: t('sms.title'),
    href: '/sms',
  },
  {
    title: 'New Message',
  },
];
  const { flash } = usePage().props as any;
  const [singleRecipient, setSingleRecipient] = useState('');
  const [singleRecipientError, setSingleRecipientError] = useState('');
  const [bulkTags, setBulkTags] = useState<PhoneTag[]>([]);
  const [messageLength, setMessageLength] = useState(0);
  const [selectedContactLists, setSelectedContactLists] = useState<number[]>([]);
  const [loadingContacts, setLoadingContacts] = useState(false);
  const [showValidationModal, setShowValidationModal] = useState(false);
  const [showResultsModal, setShowResultsModal] = useState(false);
  const [validationIssues, setValidationIssues] = useState<Array<{ phone: string; error: string }>>([]);
  const [sendResults, setSendResults] = useState<any>(null);

  const { data, setData, post, processing, errors, transform } = useForm({
    from_phone_id: phoneNumbers[0]?.id?.toString() || '',
    recipients: [] as string[],
    message: '',
  });

  // Check for SMS results from backend
  useEffect(() => {
    if (flash?.smsResults) {
      setSendResults(flash.smsResults);
      setShowResultsModal(true);

      // Reset forms after showing results
      setBulkTags([]);
      setSingleRecipient('');
      setData('message', '');
      setMessageLength(0);
    }
  }, [flash]);

  const handleMessageChange = (value: string) => {
    setData('message', value);
    setMessageLength(value.length);
  };

  const handleSingleRecipientChange = (value: string) => {
    setSingleRecipient(value);

    // Validate on change
    if (value.trim()) {
      const validation = validatePhoneNumber(value.trim());
      if (!validation.isValid) {
        setSingleRecipientError(validation.error || t('sms.invalid_phone_number'));
      } else {
        setSingleRecipientError('');
      }
    } else {
      setSingleRecipientError('');
    }
  };

  const handleContactListToggle = (listId: number) => {
    setSelectedContactLists((prev) =>
      prev.includes(listId)
        ? prev.filter((id) => id !== listId)
        : [...prev, listId]
    );
  };

  const handleImportFromContactLists = async () => {
    if (selectedContactLists.length === 0) {
      toast.error(t('sms.select_at_least_one_list'));
      return;
    }

    setLoadingContacts(true);
    try {
      const response = await axios.post('/sms/contact-list-numbers', {
        contact_list_ids: selectedContactLists,
      });

      const phoneNumbers = response.data.phone_numbers || [];

      if (phoneNumbers.length === 0) {
        toast.warning(t('sms.no_phone_numbers_in_lists'));
        return;
      }

      // Convert to tags with validation
      const newTags: PhoneTag[] = phoneNumbers.map((phone: string) => {
        const validation = validatePhoneNumber(phone);
        return {
          phone,
          isValid: validation.isValid,
          error: validation.error,
        };
      });

      // Merge with existing tags, avoid duplicates
      const existingPhones = new Set(bulkTags.map((t) => t.phone));
      const uniqueNewTags = newTags.filter((t) => !existingPhones.has(t.phone));

      setBulkTags([...bulkTags, ...uniqueNewTags]);
      toast.success(t('sms.imported_phone_numbers', { count: uniqueNewTags.length }));
      setSelectedContactLists([]);
    } catch (error: any) {
      toast.error(error.response?.data?.message || t('sms.failed_to_import_contacts'));
    } finally {
      setLoadingContacts(false);
    }
  };

  const handleSubmitSingle = (e: React.FormEvent) => {
    e.preventDefault();

    const trimmed = singleRecipient.trim();
    if (!trimmed) {
      setSingleRecipientError(t('sms.enter_recipient'));
      return;
    }

    const validation = validatePhoneNumber(trimmed);
    if (!validation.isValid) {
      setValidationIssues([{ phone: trimmed, error: validation.error || t('sms.invalid_format') }]);
      setShowValidationModal(true);
      return;
    }

    // Valid single recipient, send directly
    transform(formData => ({ ...formData, recipients: [validation.formatted!] }));
    post('/sms/send', {
      onSuccess: () => {
        toast.success(t('sms.message_sent_successfully'));
      },
      onError: (errors) => {
        console.error('Send error:', errors);
        toast.error(t('sms.failed_to_send_message'));
      },
    });
  };

  const handleSubmitBulk = (e: React.FormEvent) => {
    e.preventDefault();

    if (bulkTags.length === 0) {
      toast.error(t('sms.add_at_least_one_recipient'));
      return;
    }

    const validNumbers = bulkTags.filter((t) => t.isValid).map((t) => t.phone);
    const invalidNumbers = bulkTags
      .filter((t) => !t.isValid)
      .map((t) => ({
        phone: t.phone,
        error: t.error || t('sms.invalid_format'),
      }));

    // If there are invalid numbers, show validation modal
    if (invalidNumbers.length > 0) {
      setValidationIssues(invalidNumbers);
      setShowValidationModal(true);
      return;
    }

    // All valid, send directly
    transform(formData => ({ ...formData, recipients: validNumbers }));
    post('/sms/send', {
      onSuccess: () => {
        toast.success(t('sms.messages_sent_successfully'));
      },
      onError: (errors) => {
        console.error('Send error:', errors);
        toast.error(t('sms.failed_to_send_messages'));
      },
    });
  };

  const handleSendValidOnly = () => {
    setShowValidationModal(false);

    const validNumbers = bulkTags.filter((t) => t.isValid).map((t) => t.phone);

    if (validNumbers.length === 0) {
      toast.error(t('sms.no_valid_numbers'));
      return;
    }

    transform(formData => ({ ...formData, recipients: validNumbers }));
    post('/sms/send', {
      onSuccess: () => {
        toast.success(t('sms.messages_sent_to_valid'));
      },
      onError: (errors) => {
        console.error('Send error:', errors);
        toast.error(t('sms.failed_to_send_messages'));
      },
    });
  };

  const handleFixNumbers = () => {
    setShowValidationModal(false);
    toast.info(t('sms.fix_invalid_numbers'));
  };

  const messageSegments = Math.ceil(messageLength / 160);

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('sms.new_message')} />

      <div className="container mx-auto p-6 space-y-6">
        <div className="flex items-center gap-4">
          <Button variant="ghost" size="sm" onClick={() => router.visit('/sms')}>
            <ArrowLeft className="mr-2 h-4 w-4" />
            {t('common.back')}
          </Button>
          <div>
            <h1 className="text-3xl font-bold tracking-tight text-foreground">{t('sms.new_message')}</h1>
            <p className="text-muted-foreground mt-1">{t('sms.compose_description')}</p>
          </div>
        </div>

        {phoneNumbers.length === 0 ? (
          <Card className="border-border bg-card">
            <CardContent className="flex flex-col items-center justify-center py-12">
              <div className="text-center">
                <h3 className="text-lg font-semibold text-foreground mb-2">{t('sms.no_phone_numbers_title')}</h3>
                <p className="text-muted-foreground mb-4">
                  {t('sms.no_phone_numbers_compose')}
                </p>
                <Button onClick={() => router.visit('/numbers/available')}>
                  <Plus className="mr-2 h-4 w-4" />
                  {t('sms.add_phone_number')}
                </Button>
              </div>
            </CardContent>
          </Card>
        ) : (
          <Tabs defaultValue="single" className="space-y-6">
            <TabsList className="grid w-full max-w-md grid-cols-2">
              <TabsTrigger value="single">
                <User className="mr-2 h-4 w-4" />
                {t('sms.single')}
              </TabsTrigger>
              <TabsTrigger value="bulk">
                <Users className="mr-2 h-4 w-4" />
                {t('sms.bulk')}
              </TabsTrigger>
            </TabsList>

            {/* Single SMS */}
            <TabsContent value="single">
              <form onSubmit={handleSubmitSingle} className="space-y-6">
                <Card className="border-border bg-card">
                  <CardHeader>
                    <CardTitle className="text-foreground">{t('sms.single_recipient')}</CardTitle>
                    <CardDescription>{t('sms.single_recipient_description')}</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-6">
                    {/* From Number */}
                    <div className="space-y-2">
                      <Label htmlFor="from_phone_single" className="text-foreground">
                        {t('sms.from')} <span className="text-destructive">*</span>
                      </Label>
                      <Select
                        value={data.from_phone_id}
                        onValueChange={(value) => setData('from_phone_id', value)}
                      >
                        <SelectTrigger className={errors.from_phone_id ? 'border-destructive' : ''}>
                          <SelectValue placeholder={t('sms.select_phone_number')} />
                        </SelectTrigger>
                        <SelectContent>
                          {phoneNumbers.map((phone) => (
                            <SelectItem key={phone.id} value={phone.id.toString()}>
                              {phone.friendly_name || phone.number}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      {errors.from_phone_id && (
                        <p className="text-sm text-destructive">{errors.from_phone_id}</p>
                      )}
                    </div>

                    {/* Recipient */}
                    <div className="space-y-2">
                      <Label htmlFor="recipient" className="text-foreground">
                        {t('sms.to')} <span className="text-destructive">*</span>
                      </Label>
                      <Input
                        id="recipient"
                        type="text"
                        value={singleRecipient}
                        onChange={(e) => handleSingleRecipientChange(e.target.value)}
                        placeholder="+12345678900"
                        className={singleRecipientError ? 'border-destructive' : ''}
                      />
                      {singleRecipientError && (
                        <p className="text-sm text-destructive">{singleRecipientError}</p>
                      )}
                      <p className="text-xs text-muted-foreground">
                        {t('sms.phone_format_hint')}
                      </p>
                    </div>

                    {/* Message */}
                    <div className="space-y-2">
                      <div className="flex items-center justify-between">
                        <Label htmlFor="message" className="text-foreground">
                          {t('sms.message')} <span className="text-destructive">*</span>
                        </Label>
                        <span className="text-sm text-muted-foreground">
                          {messageLength}/1600 • {messageSegments} {t('sms.segment', { count: messageSegments })}
                        </span>
                      </div>
                      <Textarea
                        id="message"
                        value={data.message}
                        onChange={(e) => handleMessageChange(e.target.value)}
                        placeholder={t('sms.type_message_placeholder')}
                        className={`min-h-[150px] resize-none ${errors.message ? 'border-destructive' : ''}`}
                        maxLength={1600}
                      />
                      {errors.message && <p className="text-sm text-destructive">{errors.message}</p>}
                    </div>

                    <Button type="submit" disabled={processing || !data.message || !singleRecipient} className="w-full">
                      {processing ? (
                        <>
                          <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                          {t('sms.sending')}
                        </>
                      ) : (
                        <>
                          <Send className="mr-2 h-4 w-4" />
                          {t('sms.send_sms')}
                        </>
                      )}
                    </Button>
                  </CardContent>
                </Card>
              </form>
            </TabsContent>

            {/* Bulk SMS */}
            <TabsContent value="bulk">
              <form onSubmit={handleSubmitBulk} className="space-y-6">
                <Card className="border-border bg-card">
                  <CardHeader>
                    <CardTitle className="text-foreground">{t('sms.bulk_recipients')}</CardTitle>
                    <CardDescription>{t('sms.bulk_recipients_description')}</CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-6">
                    {/* From Number */}
                    <div className="space-y-2">
                      <Label htmlFor="from_phone_bulk" className="text-foreground">
                        {t('sms.from')} <span className="text-destructive">*</span>
                      </Label>
                      <Select
                        value={data.from_phone_id}
                        onValueChange={(value) => setData('from_phone_id', value)}
                      >
                        <SelectTrigger className={errors.from_phone_id ? 'border-destructive' : ''}>
                          <SelectValue placeholder={t('sms.select_phone_number')} />
                        </SelectTrigger>
                        <SelectContent>
                          {phoneNumbers.map((phone) => (
                            <SelectItem key={phone.id} value={phone.id.toString()}>
                              {phone.friendly_name || phone.number}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      {errors.from_phone_id && (
                        <p className="text-sm text-destructive">{errors.from_phone_id}</p>
                      )}
                    </div>

                    {/* Import from Contact Lists */}
                    {contactLists.length > 0 && (
                      <div className="space-y-3">
                        <Label className="text-foreground">{t('sms.import_from_contact_lists')}</Label>
                        <div className="rounded-lg border border-border bg-muted/50 p-4 space-y-3">
                          <div className="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-[200px] overflow-y-auto">
                            {contactLists.map((list) => (
                              <div
                                key={list.id}
                                className="flex items-start space-x-3 rounded-md border border-border bg-background p-3 hover:bg-accent/50 transition-colors"
                              >
                                <Checkbox
                                  id={`list-${list.id}`}
                                  checked={selectedContactLists.includes(list.id)}
                                  onCheckedChange={() => handleContactListToggle(list.id)}
                                />
                                <div className="flex-1">
                                  <label
                                    htmlFor={`list-${list.id}`}
                                    className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 cursor-pointer"
                                  >
                                    {list.name}
                                  </label>
                                  <p className="text-xs text-muted-foreground mt-1">
                                    {t('sms.contacts_count', { count: list.contacts_count })}
                                  </p>
                                </div>
                              </div>
                            ))}
                          </div>
                          <Button
                            type="button"
                            onClick={handleImportFromContactLists}
                            disabled={selectedContactLists.length === 0 || loadingContacts}
                            variant="outline"
                            className="w-full"
                          >
                            {loadingContacts ? (
                              <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                {t('sms.importing')}
                              </>
                            ) : (
                              <>
                                <List className="mr-2 h-4 w-4" />
                                {t('sms.import_selected', { count: selectedContactLists.length })}
                              </>
                            )}
                          </Button>
                        </div>
                      </div>
                    )}

                    {/* Phone Number Tag Input */}
                    <div className="space-y-2">
                      <Label className="text-foreground">
                        {t('sms.recipients')} <span className="text-destructive">*</span>
                      </Label>
                      <PhoneNumberTagInput value={bulkTags} onChange={setBulkTags} />
                    </div>

                    {/* Message */}
                    <div className="space-y-2">
                      <div className="flex items-center justify-between">
                        <Label htmlFor="message_bulk" className="text-foreground">
                          {t('sms.message')} <span className="text-destructive">*</span>
                        </Label>
                        <span className="text-sm text-muted-foreground">
                          {messageLength}/1600 • {messageSegments} {t('sms.segment', { count: messageSegments })}
                        </span>
                      </div>
                      <Textarea
                        id="message_bulk"
                        value={data.message}
                        onChange={(e) => handleMessageChange(e.target.value)}
                        placeholder={t('sms.type_message_placeholder')}
                        className={`min-h-[150px] resize-none ${errors.message ? 'border-destructive' : ''}`}
                        maxLength={1600}
                      />
                      {errors.message && <p className="text-sm text-destructive">{errors.message}</p>}
                    </div>

                    <Button
                      type="submit"
                      disabled={processing || !data.message || bulkTags.length === 0}
                      className="w-full"
                    >
                      {processing ? (
                        <>
                          <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                          {t('sms.sending')}
                        </>
                      ) : (
                        <>
                          <Send className="mr-2 h-4 w-4" />
                          {t('sms.send_to_recipients', { count: bulkTags.filter((t) => t.isValid).length })}
                        </>
                      )}
                    </Button>
                  </CardContent>
                </Card>
              </form>
            </TabsContent>
          </Tabs>
        )}
      </div>

      {/* Validation Modal */}
      <ValidationModal
        open={showValidationModal}
        onClose={() => setShowValidationModal(false)}
        validCount={bulkTags.filter((t) => t.isValid).length}
        invalidNumbers={validationIssues}
        onSendValid={handleSendValidOnly}
        onFixNumbers={handleFixNumbers}
      />

      {/* Results Modal */}
      {sendResults && (
        <SendResultsModal
          open={showResultsModal}
          onClose={() => {
            setShowResultsModal(false);
            setSendResults(null);
          }}
          successCount={sendResults.success || 0}
          skippedNumbers={sendResults.skippedNumbers || []}
        />
      )}
    </AppLayout>
  );
}
