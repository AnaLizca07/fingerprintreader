<?php
namespace App\Console\Commands;

use App\Models\Attendace;
use App\Services\ZKTecoService;
use Illuminate\Console\Command;

class SyncAttendace extends Command {
    protected $signature = 'attendace:sync';
    protected $description = 'Sync attendance from ZKTeco device';

    public function handle() {
        $zkTecoService = new ZKTecoService(
            config('services.zkteco.ip'),
            config('services.zkteco.port'),
            config('services.zkteco.key')
        );

        if(!$zkTecoService->connect()) {
            $this->error('Could not connect to ZKTeco device');
            return 1;
        }

        try {
            $records = $zkTecoService->getAttendance();

            foreach($records as $record) {
                Attendace::updateOrCreate([
                    'user_id' => $record['user_id'],
                    'timestamp' => $record['timestamp'],
                    'punch_type' => $record['punch_type']
                ]);
            }

            $this->info('Attendance sync successful');
            return 0;
        } catch (\Exception $e) {
            $this->error('Error syncing attendance: ' . $e->getMessage());
            return 1;
        } finally {
            $zkTecoService->dissconnect();
        }
    }
}
