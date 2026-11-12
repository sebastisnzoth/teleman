<?php

namespace Database\Seeders;

use App\Models\CampaignTemplate;
use Illuminate\Database\Seeder;

class CampaignTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: templates are only seeded into an empty table.
        if (CampaignTemplate::query()->exists()) {
            return;
        }

        $templates = [
            // 1. Voice Broadcast - Flash Sale
            [
                'name' => 'Flash Sale Announcement',
                'description' => 'Promote limited-time offers and flash sales with urgency',
                'category' => 'promotional',
                'is_system_template' => true,
                'template_data' => [
                    'type' => 'text_to_speech',
                    'message' => 'Hi {{first_name}}! This is {{store_name}}. Our {{sale_type}} is NOW LIVE! Get {{discount_percentage}} off everything. Shop now at {{website}} or visit us at {{store_location}}. This offer expires at {{expiry_time}}!',
                    'voice' => 'Polly.Joanna',
                    'voice_language' => 'en-US',
                    'enable_recording' => false,
                    'enable_dtmf' => false,
                    'max_concurrent_calls' => 50,
                    'retry_attempts' => 2,
                    'retry_delay_minutes' => 60,
                    'expected_variables' => ['first_name', 'last_name'],
                    'campaign_variables' => [
                        'store_name' => 'Fashion Plus',
                        'sale_type' => 'Black Friday Sale',
                        'discount_percentage' => '50%',
                        'website' => 'fashionplus.com',
                        'store_location' => 'Main Street Mall',
                        'expiry_time' => 'midnight',
                    ],
                ],
            ],

            // 2. Voice Broadcast - Product Launch
            [
                'name' => 'New Product Launch',
                'description' => 'Announce new products or services to your customer base',
                'category' => 'promotional',
                'is_system_template' => true,
                'template_data' => [
                    'type' => 'text_to_speech',
                    'message' => 'Hi {{first_name}}, exciting news from {{company_name}}! We just launched {{product_name}}. {{product_benefit}}. Be among the first to get it! Visit {{website}} or call us at {{phone_number}}. {{special_offer}}',
                    'voice' => 'Polly.Matthew',
                    'voice_language' => 'en-US',
                    'enable_recording' => false,
                    'enable_dtmf' => true,
                    'max_concurrent_calls' => 30,
                    'retry_attempts' => 3,
                    'retry_delay_minutes' => 120,
                    'expected_variables' => ['first_name', 'last_name'],
                    'campaign_variables' => [
                        'company_name' => 'TechCorp',
                        'product_name' => 'iPhone 16 Pro',
                        'product_benefit' => 'With revolutionary AI features',
                        'website' => 'techcorp.com',
                        'phone_number' => '555-TECH',
                        'special_offer' => 'Order today and get free shipping!',
                    ],
                ],
            ],

            // 3. Lead Qualification - Demo Request
            [
                'name' => 'Demo Request Follow-up',
                'description' => 'Follow up with leads who requested product demos',
                'category' => 'lead_qualification',
                'is_system_template' => true,
                'template_data' => [
                    'type' => 'text_to_speech',
                    'message' => 'Hi {{first_name}}, thank you for requesting a demo of {{product_name}}. Press 1 to schedule your personalized demo right now, press 2 to receive more information by email, or press 3 to speak with a sales representative.',
                    'voice' => 'Polly.Joanna',
                    'voice_language' => 'en-US',
                    'enable_recording' => true,
                    'enable_dtmf' => true,
                    'max_concurrent_calls' => 10,
                    'retry_attempts' => 3,
                    'retry_delay_minutes' => 240,
                    'expected_variables' => ['first_name', 'last_name', 'email'],
                    'campaign_variables' => [
                        'product_name' => 'Enterprise CRM',
                        'lead_source' => 'website',
                        'interest_level' => 'high',
                    ],
                ],
            ],

            // 4. Lead Qualification - Upsell
            [
                'name' => 'Customer Upsell Offer',
                'description' => 'Upsell premium plans to existing customers',
                'category' => 'lead_qualification',
                'is_system_template' => true,
                'template_data' => [
                    'type' => 'text_to_speech',
                    'message' => 'Hi {{first_name}}, we noticed you\'re using our {{current_plan}} and loving {{favorite_feature}}. We have an exclusive offer: upgrade to {{upgrade_plan}} and get {{benefit}} for just {{upgrade_price}} per month. Press 1 to upgrade now and save {{savings}}, or press 2 to learn more.',
                    'voice' => 'Polly.Matthew',
                    'voice_language' => 'en-US',
                    'enable_recording' => true,
                    'enable_dtmf' => true,
                    'max_concurrent_calls' => 15,
                    'retry_attempts' => 2,
                    'retry_delay_minutes' => 180,
                    'expected_variables' => ['first_name', 'last_name'],
                    'campaign_variables' => [
                        'current_plan' => 'Basic Plan',
                        'favorite_feature' => 'email automation',
                        'upgrade_plan' => 'Professional Plan',
                        'benefit' => 'unlimited contacts',
                        'upgrade_price' => '$49',
                        'savings' => '$20',
                    ],
                ],
            ],

            // 5. Survey - NPS
            [
                'name' => 'NPS Customer Satisfaction Survey',
                'description' => 'Measure customer satisfaction with Net Promoter Score',
                'category' => 'survey',
                'is_system_template' => true,
                'template_data' => [
                    'type' => 'text_to_speech',
                    'message' => 'Hi {{first_name}}, on a scale of 0 to 9, how likely are you to recommend {{company_name}} to a friend or colleague? Press 0 for not at all likely, or 9 for extremely likely. Thank you for your feedback!',
                    'voice' => 'Polly.Joanna',
                    'voice_language' => 'en-US',
                    'enable_recording' => true,
                    'enable_dtmf' => true,
                    'max_concurrent_calls' => 20,
                    'retry_attempts' => 2,
                    'retry_delay_minutes' => 120,
                    'expected_variables' => ['first_name', 'last_name'],
                    'campaign_variables' => [
                        'company_name' => 'SuperSaaS',
                        'purchase_date' => 'Oct 15, 2025',
                    ],
                ],
            ],

            // 6. Survey - Post-Purchase
            [
                'name' => 'Post-Purchase Satisfaction',
                'description' => 'Collect feedback after customer purchases',
                'category' => 'survey',
                'is_system_template' => true,
                'template_data' => [
                    'type' => 'text_to_speech',
                    'message' => 'Hi {{first_name}}, thank you for your recent purchase of {{product_name}}. We\'d love your feedback. Press 1 if you\'re very satisfied, press 2 for satisfied, press 3 for neutral, press 4 for dissatisfied, or press 5 if you have an issue that needs immediate attention.',
                    'voice' => 'Polly.Joanna',
                    'voice_language' => 'en-US',
                    'enable_recording' => true,
                    'enable_dtmf' => true,
                    'max_concurrent_calls' => 15,
                    'retry_attempts' => 2,
                    'retry_delay_minutes' => 180,
                    'expected_variables' => ['first_name', 'last_name'],
                    'campaign_variables' => [
                        'product_name' => 'Wireless Headphones Pro',
                        'order_id' => 'ORD-12345',
                        'purchase_date' => 'Nov 3, 2025',
                    ],
                ],
            ],

            // 7. Notification - Appointment Reminder
            [
                'name' => 'Appointment Reminder',
                'description' => 'Remind customers of upcoming appointments',
                'category' => 'notification',
                'is_system_template' => true,
                'template_data' => [
                    'type' => 'text_to_speech',
                    'message' => 'Hi {{first_name}}, this is {{business_name}} reminding you of your appointment with {{provider_name}} on {{appointment_date}} at {{appointment_time}}. Press 1 to confirm your appointment, press 2 if you need to reschedule, or press 3 to cancel.',
                    'voice' => 'Polly.Joanna',
                    'voice_language' => 'en-US',
                    'enable_recording' => false,
                    'enable_dtmf' => true,
                    'max_concurrent_calls' => 30,
                    'retry_attempts' => 2,
                    'retry_delay_minutes' => 120,
                    'expected_variables' => ['first_name', 'last_name'],
                    'campaign_variables' => [
                        'business_name' => 'City Medical Center',
                        'provider_name' => 'Dr. Smith',
                        'appointment_date' => 'November 15',
                        'appointment_time' => '2:30 PM',
                        'appointment_type' => 'Annual Checkup',
                    ],
                ],
            ],

            // 8. Notification - Payment Reminder
            [
                'name' => 'Payment Due Reminder',
                'description' => 'Notify customers of upcoming or overdue payments',
                'category' => 'notification',
                'is_system_template' => true,
                'template_data' => [
                    'type' => 'text_to_speech',
                    'message' => 'Hi {{first_name}}, this is {{company_name}} billing department. Your payment of ${{amount}} for {{service}} is due on {{due_date}}. Press 1 to pay by phone, press 2 for payment options, or press 3 to speak with our billing team.',
                    'voice' => 'Polly.Matthew',
                    'voice_language' => 'en-US',
                    'enable_recording' => true,
                    'enable_dtmf' => true,
                    'max_concurrent_calls' => 20,
                    'retry_attempts' => 3,
                    'retry_delay_minutes' => 1440,
                    'expected_variables' => ['first_name', 'last_name'],
                    'campaign_variables' => [
                        'company_name' => 'Internet Plus',
                        'amount' => '89.99',
                        'service' => 'Premium Internet Plan',
                        'due_date' => 'November 15',
                        'account_number' => 'ACC-789456',
                    ],
                ],
            ],

            // 9. Notification - Service Alert
            [
                'name' => 'Service Outage Notification',
                'description' => 'Alert customers about service maintenance or outages',
                'category' => 'notification',
                'is_system_template' => true,
                'template_data' => [
                    'type' => 'text_to_speech',
                    'message' => 'Important notice: {{company_name}} will perform {{maintenance_type}} affecting {{affected_area}} on {{maintenance_date}} from {{start_time}} to {{end_time}}. {{service_type}} will be unavailable during this time. We apologize for any inconvenience.',
                    'voice' => 'Polly.Joanna',
                    'voice_language' => 'en-US',
                    'enable_recording' => false,
                    'enable_dtmf' => false,
                    'max_concurrent_calls' => 100,
                    'retry_attempts' => 2,
                    'retry_delay_minutes' => 30,
                    'expected_variables' => ['first_name', 'last_name'],
                    'campaign_variables' => [
                        'company_name' => 'City Power & Light',
                        'maintenance_type' => 'emergency maintenance',
                        'service_type' => 'electricity',
                        'affected_area' => 'downtown district',
                        'maintenance_date' => 'November 8',
                        'start_time' => '2 AM',
                        'end_time' => '6 AM',
                    ],
                ],
            ],

            // 10. Personalized - VIP Thank You
            [
                'name' => 'VIP Customer Appreciation',
                'description' => 'Thank your most valuable customers personally',
                'category' => 'personalized',
                'is_system_template' => true,
                'template_data' => [
                    'type' => 'text_to_speech',
                    'message' => 'Hi {{first_name}}, this is a personal message from our CEO. As one of our {{customer_tier}} customers for {{loyalty_years}} years, we truly appreciate your loyalty. You\'ve spent {{total_spent}} with us. As a special thank you, we\'re giving you {{exclusive_offer}}. Your dedicated account manager {{manager_name}} will call you this week.',
                    'voice' => 'Polly.Matthew',
                    'voice_language' => 'en-US',
                    'enable_recording' => false,
                    'enable_dtmf' => false,
                    'max_concurrent_calls' => 5,
                    'retry_attempts' => 2,
                    'retry_delay_minutes' => 360,
                    'expected_variables' => ['first_name', 'last_name'],
                    'campaign_variables' => [
                        'customer_tier' => 'Platinum VIP',
                        'loyalty_years' => '5',
                        'total_spent' => '$25,450',
                        'exclusive_offer' => 'a complimentary year of premium support',
                        'manager_name' => 'Jennifer Lopez',
                    ],
                ],
            ],

            // 11. Personalized - Win-Back
            [
                'name' => 'Inactive Customer Win-Back',
                'description' => 'Re-engage customers who haven\'t used your service recently',
                'category' => 'personalized',
                'is_system_template' => true,
                'template_data' => [
                    'type' => 'text_to_speech',
                    'message' => 'Hi {{first_name}}, we noticed you haven\'t {{action}} in {{days_inactive}} days. We miss you! Come back today and we\'ll give you {{win_back_offer}}. You loved our {{favorite_feature}} before. Press 1 to reactivate your account now, or press 2 to tell us what we can do better.',
                    'voice' => 'Polly.Joanna',
                    'voice_language' => 'en-US',
                    'enable_recording' => true,
                    'enable_dtmf' => true,
                    'max_concurrent_calls' => 15,
                    'retry_attempts' => 2,
                    'retry_delay_minutes' => 240,
                    'expected_variables' => ['first_name', 'last_name'],
                    'campaign_variables' => [
                        'action' => 'logged in',
                        'days_inactive' => '45',
                        'win_back_offer' => '50% off for 3 months',
                        'favorite_feature' => 'analytics dashboard',
                        'last_login' => 'Sept 20, 2025',
                    ],
                ],
            ],

            // 12. Personalized - Birthday
            [
                'name' => 'Birthday Celebration Call',
                'description' => 'Wish customers happy birthday with a special offer',
                'category' => 'personalized',
                'is_system_template' => true,
                'template_data' => [
                    'type' => 'text_to_speech',
                    'message' => 'Happy birthday, {{first_name}}! It\'s been {{years_with_us}} amazing years having you as a customer. As our birthday gift to you, enjoy {{birthday_offer}}. Use code {{promo_code}} at checkout. Valid until {{expiry_date}}. Have a wonderful day!',
                    'voice' => 'Polly.Joanna',
                    'voice_language' => 'en-US',
                    'enable_recording' => false,
                    'enable_dtmf' => false,
                    'max_concurrent_calls' => 10,
                    'retry_attempts' => 1,
                    'retry_delay_minutes' => 180,
                    'expected_variables' => ['first_name', 'last_name'],
                    'campaign_variables' => [
                        'years_with_us' => '3',
                        'birthday_offer' => 'a free month of premium service',
                        'promo_code' => 'BDAY2025',
                        'expiry_date' => 'November 30',
                    ],
                ],
            ],
        ];

        foreach ($templates as $template) {
            CampaignTemplate::create($template);
        }

        $this->command->info('Created '.count($templates).' campaign templates');
    }
}
