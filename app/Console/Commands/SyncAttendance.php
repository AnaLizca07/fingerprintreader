<?php

namespace App\Console\Commands;

use App\Http\Controllers\AttendanceController;
use Illuminate\Console\Command;

class SyncAttendance extends Command
{
    protected $signature = 'attendance:sync';
    protected $description = 'Sync attendance records from ZKTeco device';

    public function handle()
    {
        try {
            $this->info('Starting sync process...');
            
            $controller = new AttendanceController();
            $response = $controller->sync();
            
            $this->info('Sync completed successfully');
            $this->info($response->getContent());
            
        } catch (\Exception $e) {
            $this->error('Error during sync: ' . $e->getMessage());
            $this->error('Stack trace:');
            $this->error($e->getTraceAsString());
        }
    }
}