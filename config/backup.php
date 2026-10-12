<?php

return [

    'disk' => env('BACKUP_DISK', env('DB_BACKUP_DISK', 's3')),

    'path' => env(
        'BACKUP_PATH',
        env('DB_BACKUP_PATH', env('DB_BACKUP_PATH_PREFIX', 'database-backups'))
    ),

    'retention_hours' => (int) env(
        'DB_BACKUP_RETENTION_HOURS',
        24
    ),

    'mysqldump_path' => env(
        'MYSQLDUMP_PATH',
        'mysqldump'
    ),

    'failure_email' => env(
        'DB_BACKUP_FAILURE_EMAIL',
        env('DB_BACKUP_EMAIL_RECIPIENTS', env('MAIL_FROM_ADDRESS'))
    ),

    'schedule_timezone' => env(
        'DB_BACKUP_SCHEDULE_TIMEZONE',
        'Asia/Kolkata'
    ),
];