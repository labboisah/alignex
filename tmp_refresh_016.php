<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$path=public_path('downloads/offline-server/AlignEx-Center-Server-0.1.6-win-unpacked.zip');
App\Models\AppRelease::query()->where('artifact','server')->update(['is_active'=>false,'updated_at'=>now()]);
App\Models\AppRelease::query()->updateOrCreate(['artifact'=>'server','version'=>'0.1.6'],['filename'=>basename($path),'file_path'=>'downloads/offline-server/'.basename($path),'size_bytes'=>filesize($path),'sha256'=>hash_file('sha256',$path),'release_notes'=>'AlignEx Center Server 0.1.6 updated autoboot readiness release.','is_active'=>true,'published_at'=>now()]);
echo "SERVER_0.1.6_REFRESHED\n";
