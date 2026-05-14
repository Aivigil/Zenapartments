<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ----- Upcoming reminders -----
            [
                'code' => 'reminder_upcoming_t15',
                'name' => '15 days before due — friendly heads-up',
                'channel' => 'email',
                'subject' => 'Friendly reminder: payment due {{due_date}}',
                'body' => "Dear {{client_name}},\n\nA quick reminder that an installment of {{amount_with_symbol}} for booking {{booking_code}} ({{item}}) is due on {{due_date}}.\n\nNo action needed today — this is just a heads-up so you can plan.\n\nThank you,\nZen Retreats",
            ],
            [
                'code' => 'reminder_upcoming_t15',
                'name' => '15 days before due — SMS',
                'channel' => 'sms',
                'body' => 'Zen Retreats: {{amount_with_symbol}} due on {{due_date}} for booking {{booking_code}}. Plan ahead — no action needed today.',
            ],
            [
                'code' => 'reminder_upcoming_t7',
                'name' => '7 days before due',
                'channel' => 'email',
                'subject' => 'Payment of {{amount_with_symbol}} due in 7 days',
                'body' => "Dear {{client_name}},\n\nYour installment of {{amount_with_symbol}} for booking {{booking_code}} ({{item}}) is due in 7 days, on {{due_date}}.\n\nPlease ensure funds are arranged. Bank account details are available in your portal account.\n\nThank you,\nZen Retreats",
            ],
            [
                'code' => 'reminder_upcoming_t7',
                'name' => '7 days before due — SMS',
                'channel' => 'sms',
                'body' => 'Zen Retreats: {{amount_with_symbol}} due {{due_date}} (7 days). Booking {{booking_code}}. Reply HELP if you need assistance.',
            ],
            [
                'code' => 'reminder_upcoming_t3',
                'name' => '3 days before due',
                'channel' => 'email',
                'subject' => 'Reminder: {{amount_with_symbol}} due in 3 days',
                'body' => "Dear {{client_name}},\n\nA reminder that your installment of {{amount_with_symbol}} for booking {{booking_code}} is due on {{due_date}} — 3 days from today.\n\nThank you,\nZen Retreats",
            ],
            [
                'code' => 'reminder_upcoming_t3',
                'name' => '3 days before due — SMS',
                'channel' => 'sms',
                'body' => 'Zen Retreats: {{amount_with_symbol}} due {{due_date}} (3 days). Booking {{booking_code}}.',
            ],
            [
                'code' => 'reminder_upcoming_t1',
                'name' => '1 day before due',
                'channel' => 'email',
                'subject' => 'Payment due tomorrow',
                'body' => "Dear {{client_name}},\n\nYour installment of {{amount_with_symbol}} for booking {{booking_code}} is due tomorrow, {{due_date}}.\n\nIf you've already paid, please disregard.\n\nThank you,\nZen Retreats",
            ],
            [
                'code' => 'reminder_upcoming_t1',
                'name' => '1 day before due — SMS',
                'channel' => 'sms',
                'body' => 'Zen Retreats: {{amount_with_symbol}} due tomorrow. Booking {{booking_code}}.',
            ],

            // ----- Overdue escalation -----
            [
                'code' => 'reminder_overdue_d1',
                'name' => '1 day overdue — polite',
                'channel' => 'email',
                'subject' => 'Payment of {{amount_with_symbol}} overdue',
                'body' => "Dear {{client_name}},\n\nWe noticed that the installment of {{amount_with_symbol}} for booking {{booking_code}} due on {{due_date}} hasn't been received yet. If you've already paid, kindly share the deposit slip so we can reconcile.\n\nIf not, please arrange payment at the earliest.\n\nThank you,\nZen Retreats",
            ],
            [
                'code' => 'reminder_overdue_d1',
                'name' => '1 day overdue — SMS',
                'channel' => 'sms',
                'body' => 'Zen Retreats: {{amount_with_symbol}} for {{booking_code}} due {{due_date}} not yet received. If paid, share slip. Else, please arrange.',
            ],
            [
                'code' => 'reminder_overdue_d7',
                'name' => '7 days overdue — firm',
                'channel' => 'email',
                'subject' => 'Action required: {{amount_with_symbol}} overdue 7 days',
                'body' => "Dear {{client_name}},\n\nThe installment of {{amount_with_symbol}} for booking {{booking_code}} (due {{due_date}}) is now 7 days overdue.\n\nPlease arrange payment immediately. Continued non-payment may attract late fees per the agreement.\n\nFor any clarification, our finance team is reachable on the contact in your portal account.\n\nThank you,\nZen Retreats",
            ],
            [
                'code' => 'reminder_overdue_d7',
                'name' => '7 days overdue — SMS',
                'channel' => 'sms',
                'body' => 'Zen Retreats: {{amount_with_symbol}} overdue 7 days for {{booking_code}}. Please arrange payment. Late fees may apply.',
            ],
            [
                'code' => 'reminder_overdue_d15',
                'name' => '15 days overdue — finance task',
                'channel' => 'email',
                'subject' => 'Urgent: payment overdue 15 days',
                'body' => "Dear {{client_name}},\n\nDespite earlier reminders, the installment of {{amount_with_symbol}} for booking {{booking_code}} remains unpaid 15 days past the due date of {{due_date}}.\n\nA member of our finance team will call you within the next two working days. To avoid further escalation, please make the payment today.\n\nZen Retreats Finance",
            ],
            [
                'code' => 'reminder_overdue_d15',
                'name' => '15 days overdue — SMS',
                'channel' => 'sms',
                'body' => 'Zen Retreats: URGENT — {{amount_with_symbol}} overdue 15 days for {{booking_code}}. Finance will call. Please pay today.',
            ],
            [
                'code' => 'reminder_overdue_d30',
                'name' => '30 days overdue — management',
                'channel' => 'email',
                'subject' => 'FINAL NOTICE: payment overdue 30 days — booking {{booking_code}}',
                'body' => "Dear {{client_name}},\n\nThis is a final notice: {{amount_with_symbol}} for booking {{booking_code}} is now 30 days overdue (originally due {{due_date}}).\n\nWithout payment or contact within the next 7 days, the booking may be subject to formal recovery action as per the signed agreement.\n\nPlease contact us today to resolve.\n\nManagement,\nZen Retreats",
            ],
            [
                'code' => 'reminder_overdue_d30',
                'name' => '30 days overdue — SMS',
                'channel' => 'sms',
                'body' => 'Zen Retreats FINAL NOTICE: {{amount_with_symbol}} overdue 30 days for {{booking_code}}. Pay or contact us within 7 days to avoid recovery action.',
            ],

            // ----- Payment received -----
            [
                'code' => 'payment_received',
                'name' => 'Payment received receipt',
                'channel' => 'email',
                'subject' => 'Payment received — receipt {{receipt_code}}',
                'body' => "Dear {{client_name}},\n\nWe've received your payment of {{amount_with_symbol}} against booking {{booking_code}}.\n\nReceipt: {{receipt_code}}\nDate: {{received_at}}\nChannel: {{channel}}\n\nThank you,\nZen Retreats",
            ],
            [
                'code' => 'payment_received',
                'name' => 'Payment received — SMS',
                'channel' => 'sms',
                'body' => 'Zen Retreats: Received {{amount_with_symbol}} for {{booking_code}}. Receipt {{receipt_code}}. Thank you!',
            ],

            // ----- Monthly statement -----
            [
                'code' => 'statement_monthly',
                'name' => 'Monthly statement',
                'channel' => 'email',
                'subject' => 'Your Zen Retreats statement — {{period}}',
                'body' => "Dear {{client_name}},\n\nYour account statement for {{period}} is attached.\n\nOutstanding balance: {{outstanding}}\n\nLog in to your portal anytime to download the latest statement: https://portal.zenretreatspk.com\n\nZen Retreats",
            ],
        ];

        foreach ($templates as $t) {
            NotificationTemplate::updateOrCreate(
                ['code' => $t['code'], 'channel' => $t['channel']],
                $t
            );
        }
    }
}
