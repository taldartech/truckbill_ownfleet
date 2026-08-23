<?php

return [

    'disk' => env('DB_BACKUP_DISK', 'local'),

    'path' => env(
        'DB_BACKUP_PATH',
        'database-backups'
    ),

    'retention_hours' => (int) env(
        'DB_BACKUP_RETENTION_HOURS',
        24
    ),

    'mysqldump_path' => env(
        'MYSQLDUMP_PATH',
        'mysqldump'
    ),

];