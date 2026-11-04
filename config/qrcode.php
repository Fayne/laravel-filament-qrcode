<?php

return [
    'allowed_types' => ['wechat_mini_app', 'web_url'],
    'storage_disks' => ['public'],
    'default_path' => env('QRCODE_DEFAULT_STORAGE_PATH', 'images/qrcodes'),
];
