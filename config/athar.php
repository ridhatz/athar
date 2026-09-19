<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Display time zone
    |--------------------------------------------------------------------------
    |
    | Everything is STORED in UTC. This is only the zone people read times in,
    | and the zone a bare "deadline: 12 October" is interpreted in (end of that
    | day). Use a zone without daylight saving (Asia/Jakarta does not have it),
    | because the web form turns a chosen date into a fixed UTC offset.
    |
    */

    'timezone' => env('ATHAR_TIMEZONE', 'Asia/Jakarta'),

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Channels that carry every notification: "database" is the in-app bell, "mail" is email.
    | Adding another (e.g. WhatsApp) means writing a channel class and listing it here.
    | Email uses MAIL_MAILER from .env; with MAIL_MAILER=log messages are only written to the log.
    |
    */

    'notifications' => [
        'channels' => array_filter(explode(',', (string) env('ATHAR_NOTIFY_CHANNELS', 'database,mail'))),
        // Remind when a deadline is closer than this many hours.
        'deadline_warning_hours' => (int) env('ATHAR_DEADLINE_WARNING_HOURS', 24),
    ],
    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | Files are stored on a PRIVATE disk (never under public/) and served only through an
    | authorised route. Both the extension and the content-sniffed type must be on the list,
    | so a script renamed to .png is refused. SVG and HTML are deliberately not allowed:
    | they can carry scripts.
    |
    */

    'attachments' => [
        'disk' => env('ATHAR_ATTACHMENT_DISK', 'local'),
        'max_kb' => (int) env('ATHAR_ATTACHMENT_MAX_KB', 20480),

        // extension => acceptable sniffed MIME types
        'types' => [
            'pdf' => ['application/pdf'],
            'png' => ['image/png'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'txt' => ['text/plain'],
            'csv' => ['text/plain', 'text/csv', 'application/csv'],
            'zip' => ['application/zip', 'application/x-zip-compressed'],
            'mp4' => ['video/mp4'],
            'mov' => ['video/quicktime', 'video/mp4'],
            'mp3' => ['audio/mpeg'],
            // Office files are ZIP containers (docx/xlsx/pptx) or OLE2 (doc/xls/ppt); detection varies by libmagic version.
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
            'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office', 'application/octet-stream'],
            'xls' => ['application/vnd.ms-excel', 'application/msword', 'application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office', 'application/octet-stream'],
            'ppt' => ['application/vnd.ms-powerpoint', 'application/msword', 'application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office', 'application/octet-stream'],
        ],
    ],

];
