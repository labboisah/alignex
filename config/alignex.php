<?php

$workspaceRoot = dirname(base_path());

return [
    'apps' => [
        'offline_server_path' => env('ALIGNEX_OFFLINE_SERVER_PATH', $workspaceRoot.DIRECTORY_SEPARATOR.'offline-server'),
        'offline_server_download_url' => env('ALIGNEX_OFFLINE_SERVER_DOWNLOAD_URL'),
        'candidate_app_path' => env('ALIGNEX_CANDIDATE_APP_PATH', $workspaceRoot.DIRECTORY_SEPARATOR.'candidate-app'),
    ],
];
