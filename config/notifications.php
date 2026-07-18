<?php

return [
    'dry_run' => env('NOTIFICATIONS_DRY_RUN', true),

    'default_channels' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('NOTIFICATIONS_DEFAULT_CHANNELS', 'email,sms')),
    ))),

    'types' => [
        'candidate_exam_details' => [
            'name' => 'Candidate exam details',
            'channels' => ['email', 'sms'],
            'email_subject' => 'Exam Details: {{ exam_title }}',
            'email_body' => '<p>Hello {{ candidate_name }},</p><p>Your exam has been scheduled. Please review the details below and keep this message for reference.</p><table class="details" role="presentation"><tr><th>Exam</th><td>{{ exam_title }}</td></tr><tr><th>Date/Time</th><td>{{ start_time }}</td></tr><tr><th>Duration</th><td>{{ duration }}</td></tr><tr><th>Center</th><td>{{ center_name }}</td></tr><tr><th>Access Code</th><td><strong>{{ exam_code }}</strong></td></tr><tr><th>Registration No.</th><td>{{ registration_number }}</td></tr></table><p>Please arrive early, follow supervisor instructions, and keep your access details private.</p><p>Regards,<br>{{ organization_name }}</p>',
            'sms_body' => 'AlignEx: {{ exam_title }} starts {{ start_time }}. Code: {{ exam_code }}. Reg No: {{ registration_number }}.',
        ],

        'candidate_exam_reminder' => [
            'name' => 'Candidate exam reminder',
            'channels' => ['email', 'sms'],
            'email_subject' => 'Reminder: {{ exam_title }} starts {{ start_time }}',
            'email_body' => '<p>Hello {{ candidate_name }},</p><p>This is a reminder that your exam will start soon.</p><table class="details" role="presentation"><tr><th>Exam</th><td>{{ exam_title }}</td></tr><tr><th>Start Time</th><td>{{ start_time }}</td></tr><tr><th>Center</th><td>{{ center_name }}</td></tr><tr><th>Access Code</th><td><strong>{{ exam_code }}</strong></td></tr></table><p>Please be ready before the start time and make sure your device, power, and internet connection are stable.</p><p>Regards,<br>{{ organization_name }}</p>',
            'sms_body' => 'Reminder: {{ exam_title }} starts {{ start_time }}. Center: {{ center_name }}. Code: {{ exam_code }}.',
        ],

        'admin_application_received' => [
            'name' => 'Admin application received',
            'channels' => ['email', 'sms'],
            'email_subject' => 'Application Received: {{ application_name }}',
            'email_body' => '<p>Hello {{ admin_name }},</p><p>Your AlignEx application has been received and is awaiting review.</p><table class="details" role="presentation"><tr><th>Application</th><td>{{ application_name }}</td></tr><tr><th>Status</th><td>Pending review</td></tr><tr><th>Submitted By</th><td>{{ submitted_by }}</td></tr><tr><th>Reference</th><td>{{ reference }}</td></tr></table><p>We will notify you after the review is completed. Please keep this reference for future communication.</p><p>Regards,<br>AlignEx</p>',
            'sms_body' => 'AlignEx: Application {{ reference }} for {{ application_name }} has been received and is pending review.',
        ],

        'admin_application_approved' => [
            'name' => 'Admin application approved',
            'channels' => ['email', 'sms'],
            'email_subject' => 'Application Approved: {{ application_name }}',
            'email_body' => '<p>Hello {{ admin_name }},</p><p>Your AlignEx application has been approved. You can now sign in and continue your setup.</p><table class="details" role="presentation"><tr><th>Application</th><td>{{ application_name }}</td></tr><tr><th>Reference</th><td>{{ reference }}</td></tr><tr><th>Approved By</th><td>{{ approved_by }}</td></tr><tr><th>Portal URL</th><td><a href="{{ portal_login_url }}">{{ portal_login_url }}</a></td></tr><tr><th>Login Email</th><td>{{ admin_email }}</td></tr></table><p>Use the password you created when submitting the application. If you no longer remember it, reset it here: <a href="{{ password_reset_url }}">{{ password_reset_url }}</a>.</p><p>For your security, AlignEx does not send account passwords by email.</p><p>Regards,<br>AlignEx</p>',
            'sms_body' => 'AlignEx: Application {{ reference }} for {{ application_name }} is approved. Login: {{ portal_login_url }} Email: {{ admin_email }}',
        ],

        'admin_application_rejected' => [
            'name' => 'Admin application rejected',
            'channels' => ['email', 'sms'],
            'email_subject' => 'Application Update: {{ application_name }}',
            'email_body' => '<p>Hello {{ admin_name }},</p><p>Your AlignEx application has been reviewed, but it was not approved at this time.</p><table class="details" role="presentation"><tr><th>Application</th><td>{{ application_name }}</td></tr><tr><th>Reference</th><td>{{ reference }}</td></tr><tr><th>Reason</th><td>{{ rejection_reason }}</td></tr></table><p>Please review the reason above and submit the required correction when ready.</p><p>Regards,<br>AlignEx</p>',
            'sms_body' => 'AlignEx: Application {{ reference }} for {{ application_name }} was not approved. Reason: {{ rejection_reason }}.',
        ],

        'general_message' => [
            'name' => 'General message',
            'channels' => ['email', 'sms'],
            'email_subject' => '{{ subject }}',
            'email_body' => '<p>Hello {{ recipient_name }},</p><p>{{ message }}</p><p>Regards,<br>{{ organization_name }}</p>',
            'sms_body' => 'AlignEx: {{ message }}',
        ],
    ],
];
