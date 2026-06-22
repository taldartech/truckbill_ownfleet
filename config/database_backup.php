<?php

return [
    'disk' => env('DB_BACKUP_DISK', 'local'),
    'path_prefix' => env('DB_BACKUP_PATH_PREFIX', 'database-backups'),
    'email_recipients' => env('DB_BACKUP_EMAIL_RECIPIENTS', ''),
    'schedule_timezone' => env('DB_BACKUP_SCHEDULE_TIMEZONE', env('APP_TIMEZONE', 'Asia/Kolkata')),
];
