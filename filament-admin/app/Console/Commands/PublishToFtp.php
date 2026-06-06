<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;

class PublishToFtp extends Command
{
    protected $signature = 'app:publish-ftp';
    protected $description = 'Export content to JSON and upload to FTP server';

    public function handle()
    {
        $this->info('Starting publication process...');

        // Step 1: Run the JSON export command
        $this->info('Step 1: Exporting content to JSON...');
        Artisan::call('app:export-json');
        $this->info(Artisan::output());

        // Step 2: Upload JSON files to FTP
        $this->info('Step 2: Uploading JSON files to FTP server...');

        $localDisk = Storage::disk('local');
        $sourcePath = 'json_exports';
        $files = $localDisk->files($sourcePath);

        if (empty($files)) {
            $this->warn('No JSON files found to upload.');
            return 1;
        }

        try {
            $ftpDisk = Storage::disk('ftp');
            foreach ($files as $file) {
                $fileName = basename($file);
                $this->line("Uploading {$fileName}...");
                $fileContents = $localDisk->get($file);
                $ftpDisk->put($fileName, $fileContents);
            }
            $this->info('All files uploaded successfully!');
        } catch (\Exception $e) {
            $this->error('FTP upload failed!');
            $this->error("Error: " . $e->getMessage());
            return 1;
        }

        $this->info('Publication process completed!');
        return 0;
    }
}
