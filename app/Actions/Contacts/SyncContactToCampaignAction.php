<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\Contact;
use Illuminate\Support\Collection;

class SyncContactToCampaignAction
{
    /**
     * Add a contact to a campaign, creating campaign_contact record
     *
     * @param Contact $contact
     * @param Campaign $campaign
     * @return CampaignContact
     */
    public function syncOne(Contact $contact, Campaign $campaign): CampaignContact
    {
        // Check if contact already in this campaign
        $existingCampaignContact = CampaignContact::where('campaign_id', $campaign->id)
            ->where('contact_id', $contact->id)
            ->first();

        if ($existingCampaignContact) {
            return $existingCampaignContact;
        }

        // Create campaign contact record
        $campaignContact = CampaignContact::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'phone_number' => $contact->phone_number,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'email' => $contact->email,
            'company' => $contact->company,
            'variables' => $contact->custom_fields ?? [],
            'status' => 'pending',
            'call_attempts' => 0,
        ]);

        // Increment contact's campaign counter
        $contact->incrementCampaigns();

        // Update campaign's total contacts
        $campaign->increment('total_contacts');

        return $campaignContact;
    }

    /**
     * Add multiple contacts to a campaign
     *
     * @param Collection|array $contacts
     * @param Campaign $campaign
     * @return array{synced: int, skipped: int}
     */
    public function syncMany($contacts, Campaign $campaign): array
    {
        $synced = 0;
        $skipped = 0;

        foreach ($contacts as $contact) {
            // Skip if contact can't be contacted (opted out or blocked)
            if (!$contact->canBeContacted()) {
                $skipped++;
                continue;
            }

            // Check if already in campaign
            $exists = CampaignContact::where('campaign_id', $campaign->id)
                ->where('contact_id', $contact->id)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $this->syncOne($contact, $campaign);
            $synced++;
        }

        return [
            'synced' => $synced,
            'skipped' => $skipped,
        ];
    }

    /**
     * Sync entire contact list to campaign
     *
     * @param \App\Models\ContactList $list
     * @param Campaign $campaign
     * @return array{synced: int, skipped: int}
     */
    public function syncList(\App\Models\ContactList $list, Campaign $campaign): array
    {
        $contacts = $list->contacts()->get();
        return $this->syncMany($contacts, $campaign);
    }

    /**
     * Sync contacts with specific tag to campaign
     *
     * @param \App\Models\ContactTag $tag
     * @param Campaign $campaign
     * @return array{synced: int, skipped: int}
     */
    public function syncTag(\App\Models\ContactTag $tag, Campaign $campaign): array
    {
        $contacts = $tag->contacts()->get();
        return $this->syncMany($contacts, $campaign);
    }
}
