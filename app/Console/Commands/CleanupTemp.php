<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class CleanupTemp extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cleanup:temp';

    /**
     * Description
     */
    protected $description = 'Cleanup temporary Python job files';

    public function handle()
    {
        $path = storage_path('app/tmp');

        if (!File::exists($path)) {
            $this->info("Temp folder not found.");
            return;
        }

        $folders = File::directories($path);

        $deleted = 0;

        foreach ($folders as $folder) {

            $lastModified = Carbon::createFromTimestamp(filemtime($folder));

            // 🔥 Hapus folder lebih dari 10 menit
            if ($lastModified->diffInMinutes(now()) > 10) {

                File::deleteDirectory($folder);
                $deleted++;

                $this->info("Deleted: " . $folder);
            }
        }

        $this->info("Cleanup done. Deleted {$deleted} folders.");
    }
}