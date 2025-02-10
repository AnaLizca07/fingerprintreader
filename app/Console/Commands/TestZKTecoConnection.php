<?php

namespace App\Console\Commands;

use App\Services\ZKTecoService;
use Illuminate\Console\Command;

class TestZKTecoConnection extends Command {
    protected $signature = 'zkteco:test';
    protected $description = 'Test ZKTeco connection';

    public function handle()
    {
        $this->info('Iniciando prueba de conexión...');

        $zkService = new ZKTecoService(
            config('services.zkteco.ip'),
            config('services.zkteco.port'),
            config('services.zkteco.key')
        );

        if ($zkService->connect()) {
            $this->info('✓ Conexión exitosa');
            
            try {
                $records = $zkService->getAttendance();
                $this->info('✓ Obtención de registros exitosa');
                $this->info('Registros encontrados: ' . count($records));
                
                // Mostrar primeros 5 registros como ejemplo
                foreach (array_slice($records, 0, 5) as $record) {
                    $this->line(json_encode($record, JSON_PRETTY_PRINT));
                }
            } catch (\Exception $e) {
                $this->error('✗ Error al obtener registros: ' . $e->getMessage());
            }
        } else {
            $this->error('✗ No se pudo conectar al dispositivo');
        }

        $zkService->dissconnect();
    }
}