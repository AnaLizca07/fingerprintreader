<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Services\ZKTecoService;
use Illuminate\Http\JsonResponse;

class AttendanceController extends Controller
{
   public function sync(): JsonResponse
   {
       try {
           $zk = new ZKTecoService(
               config('services.zkteco.ip'),
               config('services.zkteco.port'),
               config('services.zkteco.key')
           );

           $zk->connect();
           $zk->authenticate();
           $records = $zk->getAttendance();
           
           foreach ($records as $record) {
               Attendance::updateOrCreate(
                   ['user_id' => $record['user_id'], 'timestamp' => $record['timestamp']],
                   $record
               );
           }

           return response()->json(['message' => 'Attendance synced successfully']);
       } catch (\Exception $e) {
           return response()->json(['error' => $e->getMessage()], 500);
       } finally {
           if (isset($zk)) {
               $zk->close();
           }
       }
   }
}