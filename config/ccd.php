<?php

return [
    'private_disk' => env('CCD_PRIVATE_DISK', 'ccd-private'),
    'zip_v12_source_provider' => env('CCD_ZIP_V12_SOURCE_PROVIDER', App\Services\Ccd\CcdZipV12FileSourceProvider::class),
    'zip_v12_root' => env('CCD_ZIP_V12_ROOT'),
    'zip_v12_temp_root' => env('CCD_ZIP_V12_TEMP_ROOT', storage_path('app/private/ccd-zip-tmp')),
    'zip_v12_cosechas' => array_filter(array_map(
        'trim',
        explode(',', env('CCD_ZIP_V12_COSECHAS', 'CONFIANZA1,CONFIANZA2,CONFIANZA4,CONFIANZA5,CONFIANZA6,CONFIANZA7,CONFIANZA8,CONFIANZA9,CONFIANZA10,CONFIANZA11,CONFIANZA12')),
    )),
    'import_root' => env('CCD_IMPORT_ROOT', '/mnt/ccd-import/BBVA_5'),
    'import_user' => env('CCD_IMPORT_USER', 'www-data'),
    'max_file_bytes' => (int) env('CCD_MAX_FILE_BYTES', 52_428_800),
    'legacy_hosts' => [
        'drive.google.com',
        'docs.google.com',
    ],
];
