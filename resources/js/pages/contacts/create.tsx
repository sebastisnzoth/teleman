import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';

interface ContactList {
  id: number;
  name: string;
  description?: string;
}

interface Props {
  lists: ContactList[];
}

export default function ContactCreate({ lists }: Props) {
  const { t } = useTranslation();
  const { data, setData, post, processing, errors } = useForm({
    first_name: '',
    last_name: '',
    phone_number: '',
    email: '',
    company: '',
    website: '',
    facebook_url: '',
    instagram_url: '',
    linkedin_url: '',
    twitter_url: '',
    notes: '',
    list_ids: [] as number[],
  });

  const handleListToggle = (listId: number) => {
    const newListIds = data.list_ids.includes(listId)
      ? data.list_ids.filter(id => id !== listId)
      : [...data.list_ids, listId];
    setData('list_ids', newListIds);
  };

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    post('/contacts');
  };

  return (
    <AppLayout>
      <Head title={t('contacts.add')} />

      <div className="max-w-2xl mx-auto space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-3xl font-bold">{t('contacts.add')}</h1>
            <p className="text-muted-foreground">{t('contacts.create_description')}</p>
          </div>
          <Button variant="outline" onClick={() => router.get('/contacts')}>
            {t('common.cancel')}
          </Button>
        </div>

        <Card className="p-6">
          <form onSubmit={handleSubmit} className="space-y-6">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="first_name">{t('contacts.first_name_required')}</Label>
                <Input
                  id="first_name"
                  value={data.first_name}
                  onChange={(e) => setData('first_name', e.target.value)}
                  required
                />
                {errors.first_name && <p className="text-sm text-destructive">{errors.first_name}</p>}
              </div>

              <div className="space-y-2">
                <Label htmlFor="last_name">{t('contacts.last_name_required')}</Label>
                <Input
                  id="last_name"
                  value={data.last_name}
                  onChange={(e) => setData('last_name', e.target.value)}
                  required
                />
                {errors.last_name && <p className="text-sm text-destructive">{errors.last_name}</p>}
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="phone_number">{t('contacts.phone_number_required')}</Label>
              <Input
                id="phone_number"
                type="tel"
                value={data.phone_number}
                onChange={(e) => setData('phone_number', e.target.value)}
                placeholder="+1234567890"
                required
              />
              {errors.phone_number && <p className="text-sm text-destructive">{errors.phone_number}</p>}
            </div>

            <div className="space-y-2">
              <Label htmlFor="email">{t('common.email')}</Label>
              <Input
                id="email"
                type="email"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
              />
              {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
            </div>

            <div className="space-y-2">
              <Label htmlFor="company">{t('common.company')}</Label>
              <Input
                id="company"
                value={data.company}
                onChange={(e) => setData('company', e.target.value)}
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="website">{t('contacts.website')}</Label>
              <Input
                id="website"
                type="url"
                value={data.website}
                onChange={(e) => setData('website', e.target.value)}
                placeholder="https://example.com"
              />
              {errors.website && <p className="text-sm text-destructive">{errors.website}</p>}
            </div>

            {/* Social Media Links */}
            <div className="space-y-4">
              <Label className="text-base font-semibold">{t('contacts.social_media_links')}</Label>
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="facebook_url">Facebook</Label>
                  <Input
                    id="facebook_url"
                    type="url"
                    value={data.facebook_url}
                    onChange={(e) => setData('facebook_url', e.target.value)}
                    placeholder="https://facebook.com/username"
                  />
                  {errors.facebook_url && <p className="text-sm text-destructive">{errors.facebook_url}</p>}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="instagram_url">Instagram</Label>
                  <Input
                    id="instagram_url"
                    type="url"
                    value={data.instagram_url}
                    onChange={(e) => setData('instagram_url', e.target.value)}
                    placeholder="https://instagram.com/username"
                  />
                  {errors.instagram_url && <p className="text-sm text-destructive">{errors.instagram_url}</p>}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="linkedin_url">LinkedIn</Label>
                  <Input
                    id="linkedin_url"
                    type="url"
                    value={data.linkedin_url}
                    onChange={(e) => setData('linkedin_url', e.target.value)}
                    placeholder="https://linkedin.com/in/username"
                  />
                  {errors.linkedin_url && <p className="text-sm text-destructive">{errors.linkedin_url}</p>}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="twitter_url">X (Twitter)</Label>
                  <Input
                    id="twitter_url"
                    type="url"
                    value={data.twitter_url}
                    onChange={(e) => setData('twitter_url', e.target.value)}
                    placeholder="https://x.com/username"
                  />
                  {errors.twitter_url && <p className="text-sm text-destructive">{errors.twitter_url}</p>}
                </div>
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="notes">{t('contacts.notes')}</Label>
              <Textarea
                id="notes"
                value={data.notes}
                onChange={(e) => setData('notes', e.target.value)}
                rows={4}
              />
            </div>

            {/* Contact Lists Selection */}
            {lists && lists.length > 0 && (
              <div className="space-y-2">
                <Label>{t('contacts.add_to_lists')}</Label>
                <div className="border rounded-md p-4 space-y-3 max-h-48 overflow-y-auto">
                  {lists.map((list) => (
                    <div key={list.id} className="flex items-center space-x-3">
                      <Checkbox
                        id={`list-${list.id}`}
                        checked={data.list_ids.includes(list.id)}
                        onCheckedChange={() => handleListToggle(list.id)}
                      />
                      <Label htmlFor={`list-${list.id}`} className="cursor-pointer font-normal">
                        <div>
                          <p className="font-medium">{list.name}</p>
                          {list.description && (
                            <p className="text-sm text-muted-foreground">{list.description}</p>
                          )}
                        </div>
                      </Label>
                    </div>
                  ))}
                </div>
                {data.list_ids.length > 0 && (
                  <p className="text-sm text-muted-foreground">
                    {t('contacts.lists_selected', { count: data.list_ids.length })}
                  </p>
                )}
              </div>
            )}

            <div className="flex justify-end gap-2">
              <Button type="button" variant="outline" onClick={() => router.get('/contacts')}>
                {t('common.cancel')}
              </Button>
              <Button type="submit" disabled={processing}>
                {processing ? t('contacts.creating') : t('contacts.create')}
              </Button>
            </div>
          </form>
        </Card>
      </div>
    </AppLayout>
  );
}
