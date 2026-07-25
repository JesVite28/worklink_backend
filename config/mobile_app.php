<?php

return [
    'android' => [
        'apk_path' => env('ANDROID_APK_PATH', 'apps/worklink-android.apk'),
        'download_name' => env('ANDROID_APK_DOWNLOAD_NAME', 'worklink-android.apk'),
        'version_name' => env('ANDROID_APP_VERSION_NAME', '1.0.0'),
        'version_code' => (int) env('ANDROID_APP_VERSION_CODE', 1),
        'min_supported_version_code' => (int) env('ANDROID_APP_MIN_SUPPORTED_VERSION_CODE', 1),
        'force_update' => (bool) env('ANDROID_APP_FORCE_UPDATE', false),
        'changelog' => env('ANDROID_APP_CHANGELOG', ''),
        'sha256' => env('ANDROID_APP_SHA256', null),
    ],
];
