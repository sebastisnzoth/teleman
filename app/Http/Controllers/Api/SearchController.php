<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\PhoneNumber;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q', '');
        
        if (strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $results = [];
        $user = $request->user();

        // Search Menu Items
        $menuItems = $this->getMenuItems($user);
        $matchingMenus = array_filter($menuItems, function($item) use ($query) {
            return stripos($item['title'], $query) !== false || 
                   (isset($item['subtitle']) && stripos($item['subtitle'], $query) !== false);
        });
        
        foreach (array_slice($matchingMenus, 0, 5) as $menu) {
            $results[] = $menu;
        }

        // Search Customers (users with customer role)
        $customers = User::role('customer')
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%");
            })
            ->when(!$user->hasRole('admin'), function ($q) use ($user) {
                $q->where('id', $user->id); // Non-admins can only see themselves
            })
            ->limit(5)
            ->get(['id', 'name', 'email']);

        foreach ($customers as $customer) {
            $results[] = [
                'type' => 'customer',
                'id' => $customer->id,
                'title' => $customer->name,
                'subtitle' => $customer->email,
                'url' => $user->hasRole('admin') 
                    ? route('admin.users.show', $customer->id)
                    : route('profile.edit'),
            ];
        }

        // Search Agents (users with agent role) - admin only
        if ($user->hasRole('admin')) {
            $agents = User::role('agent')
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                      ->orWhere('email', 'like', "%{$query}%");
                })
                ->limit(5)
                ->get(['id', 'name', 'email']);

            foreach ($agents as $agent) {
                $results[] = [
                    'type' => 'agent',
                    'id' => $agent->id,
                    'title' => $agent->name,
                    'subtitle' => $agent->email,
                    'url' => route('admin.users.show', $agent->id),
                ];
            }
        }

        // Search Campaigns
        $campaigns = Campaign::where('name', 'like', "%{$query}%")
            ->when(!$user->hasRole('admin'), function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->limit(5)
            ->get(['id', 'name', 'status']);

        foreach ($campaigns as $campaign) {
            $results[] = [
                'type' => 'campaign',
                'id' => $campaign->id,
                'title' => $campaign->name,
                'subtitle' => ucfirst($campaign->status),
                'url' => route('campaigns.show', $campaign->id),
            ];
        }

        // Search Contacts
        $contacts = Contact::where('name', 'like', "%{$query}%")
            ->orWhere('phone', 'like', "%{$query}%")
            ->when(!$user->hasRole('admin'), function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->limit(5)
            ->get(['id', 'name', 'phone']);

        foreach ($contacts as $contact) {
            $results[] = [
                'type' => 'contact',
                'id' => $contact->id,
                'title' => $contact->name,
                'subtitle' => $contact->phone,
                'url' => route('contacts.show', $contact->id),
            ];
        }

        // Search Phone Numbers (admin only)
        if ($user->hasRole('admin')) {
            $phones = PhoneNumber::where('number', 'like', "%{$query}%")
                ->orWhere('formatted_number', 'like', "%{$query}%")
                ->orWhere('friendly_name', 'like', "%{$query}%")
                ->limit(5)
                ->get(['id', 'formatted_number', 'status']);

            foreach ($phones as $phone) {
                $results[] = [
                    'type' => 'phone',
                    'id' => $phone->id,
                    'title' => $phone->formatted_number,
                    'subtitle' => ucfirst($phone->status),
                    'url' => route('admin.numbers.index'),
                ];
            }
        }

        // Search Call Logs
        $calls = Call::with(['user', 'campaign'])
            ->where(function ($q) use ($query) {
                $q->where('from_number', 'like', "%{$query}%")
                  ->orWhere('to_number', 'like', "%{$query}%")
                  ->orWhere('twilio_call_sid', 'like', "%{$query}%");
            })
            ->when(!$user->hasRole('admin'), function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'user_id', 'campaign_id', 'from_number', 'to_number', 'status', 'direction', 'created_at']);

        foreach ($calls as $call) {
            $subtitle = ucfirst($call->direction) . ' • ' . ucfirst($call->status);
            if ($call->campaign) {
                $subtitle .= ' • ' . $call->campaign->name;
            }

            $results[] = [
                'type' => 'call',
                'id' => $call->id,
                'title' => $call->direction === 'inbound' 
                    ? "Call from {$call->from_number}" 
                    : "Call to {$call->to_number}",
                'subtitle' => $subtitle,
                'url' => route('calls.show', $call->id),
            ];
        }

        return response()->json(['results' => $results]);
    }

    /**
     * Get menu items based on user role
     */
    private function getMenuItems($user): array
    {
        $isAdmin = $user->hasRole('admin');
        $isCustomer = $user->hasRole('customer');
        
        $menus = [];

        // Dashboard
        $menus[] = [
            'type' => 'menu',
            'id' => 'dashboard',
            'title' => 'Dashboard',
            'subtitle' => 'Overview and statistics',
            'url' => route('dashboard'),
        ];

        // Communication
        $menus[] = [
            'type' => 'menu',
            'id' => 'softphone',
            'title' => 'Softphone',
            'subtitle' => 'Make and receive calls',
            'url' => '/calls/softphone',
        ];
        
        $menus[] = [
            'type' => 'menu',
            'id' => 'call-history',
            'title' => 'Call History',
            'subtitle' => 'View all calls',
            'url' => route('calls.index'),
        ];

        if ($isCustomer) {
            $menus[] = [
                'type' => 'menu',
                'id' => 'my-numbers',
                'title' => 'My Numbers',
                'subtitle' => 'Manage your phone numbers',
                'url' => '/numbers/my-numbers',
            ];
            
            $menus[] = [
                'type' => 'menu',
                'id' => 'request-number',
                'title' => 'Request New Number',
                'subtitle' => 'Get a new phone number',
                'url' => '/numbers/available',
            ];
            
            $menus[] = [
                'type' => 'menu',
                'id' => 'my-calls',
                'title' => 'My Call History',
                'subtitle' => 'Your personal call logs',
                'url' => '/my-calls',
            ];
        }

        // Campaigns
        $menus[] = [
            'type' => 'menu',
            'id' => 'campaigns',
            'title' => 'Campaigns',
            'subtitle' => 'Manage campaigns',
            'url' => route('campaigns.index'),
        ];
        
        if ($isAdmin || $isCustomer) {
            $menus[] = [
                'type' => 'menu',
                'id' => 'templates',
                'title' => 'Campaign Templates',
                'subtitle' => 'Pre-built campaign templates',
                'url' => route('campaign-templates.index'),
            ];
        }
        
        $menus[] = [
            'type' => 'menu',
            'id' => 'analytics',
            'title' => 'Analytics',
            'subtitle' => 'Campaign analytics and reports',
            'url' => '/analytics/dashboard',
        ];

        // Contacts
        $menus[] = [
            'type' => 'menu',
            'id' => 'contacts',
            'title' => 'Contacts',
            'subtitle' => 'Manage contacts',
            'url' => route('contacts.index'),
        ];
        
        if ($isAdmin || $isCustomer) {
            $menus[] = [
                'type' => 'menu',
                'id' => 'contact-lists',
                'title' => 'Contact Lists',
                'subtitle' => 'Organize contacts in lists',
                'url' => route('contact-lists.index'),
            ];
        }

        // Knowledge Base
        $menus[] = [
            'type' => 'menu',
            'id' => 'knowledge-base',
            'title' => 'Knowledge Base',
            'subtitle' => 'Help articles and guides',
            'url' => route('knowledge-base.index'),
        ];

        // Admin only menus
        if ($isAdmin) {
            $menus[] = [
                'type' => 'menu',
                'id' => 'users',
                'title' => 'Users',
                'subtitle' => 'Manage users',
                'url' => route('admin.users.index'),
            ];
            
            $menus[] = [
                'type' => 'menu',
                'id' => 'roles',
                'title' => 'Roles & Permissions',
                'subtitle' => 'Manage roles and permissions',
                'url' => route('admin.roles.index'),
            ];
            
            $menus[] = [
                'type' => 'menu',
                'id' => 'phone-numbers',
                'title' => 'Phone Numbers',
                'subtitle' => 'Manage system phone numbers',
                'url' => route('admin.numbers.index'),
            ];
            
            $menus[] = [
                'type' => 'menu',
                'id' => 'call-logs',
                'title' => 'Call Logs',
                'subtitle' => 'System-wide call logs',
                'url' => route('admin.call-logs'),
            ];
            
            $menus[] = [
                'type' => 'menu',
                'id' => 'system-health',
                'title' => 'System Health',
                'subtitle' => 'Monitor system status',
                'url' => route('admin.system-health'),
            ];
            
            $menus[] = [
                'type' => 'menu',
                'id' => 'cron-monitor',
                'title' => 'Cron Monitor',
                'subtitle' => 'Monitor scheduled tasks',
                'url' => route('admin.cron-monitor'),
            ];
        }

        // Customer only menus
        if ($isCustomer) {
            $menus[] = [
                'type' => 'menu',
                'id' => 'credits',
                'title' => 'Credits & Billing',
                'subtitle' => 'Manage credits and view transactions',
                'url' => route('credits.index'),
            ];
            
            $menus[] = [
                'type' => 'menu',
                'id' => 'top-up',
                'title' => 'Top Up Credits',
                'subtitle' => 'Add credits to your account',
                'url' => route('credits.top-up'),
            ];
        }

        // Settings
        $menus[] = [
            'type' => 'menu',
            'id' => 'settings',
            'title' => 'Settings',
            'subtitle' => 'Account and system settings',
            'url' => route('settings.index'),
        ];

        if ($isAdmin) {
            $menus[] = [
                'type' => 'menu',
                'id' => 'twilio-settings',
                'title' => 'Twilio Settings',
                'subtitle' => 'Configure integration',
                'url' => route('settings.twilio'),
            ];
        }

        return $menus;
    }
}
