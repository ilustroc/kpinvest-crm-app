<?php

return [
    'private_disk' => env('CCD_PRIVATE_DISK', 'ccd-private'),
    'import_root' => env('CCD_IMPORT_ROOT', '/mnt/ccd-import/BBVA_5'),
    'import_user' => env('CCD_IMPORT_USER', 'www-data'),
    'max_file_bytes' => (int) env('CCD_MAX_FILE_BYTES', 52_428_800),
    'legacy_hosts' => [
        'drive.google.com',
        'docs.google.com',
    ],
];
