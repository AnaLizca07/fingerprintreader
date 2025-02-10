<?php
namespace App\Services;

use const AF_INET;


class ZKTecoService {
    private $ip;
    private $port;
    private $key;
    private $socket;

    public function __construct(string $ip, int $port, string $key) {
        $this->ip = $ip;
        $this->port = (int)$port;
        $this->key = $key;
    }

    public function connect() {
        $this->socket = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) {
            throw new \Exception("Error socket: " . socket_strerror(socket_last_error()));
        }

        $result = @socket_connect($this->socket, $this->ip, $this->port);
        if ($result === false) {
            throw new \Exception("Error conexión: " . socket_strerror(socket_last_error($this->socket)));
        }

    
    return true;
    }

        public function getAttendance(): array
    {
        $command = hex2bin('5050827D10000000') . pack('N', $this->key);  // Comando específico de K30
        $sent = socket_sendto($this->socket, $command, strlen($command), 0, $this->ip, $this->port);
        if ($sent === false) {
            throw new \Exception("Error envío: " . socket_strerror(socket_last_error()));
        }
        
        $response = '';
        $received = socket_recvfrom($this->socket, $response, 4096, 0, $this->ip, $this->port);
    if ($received === false) {
        throw new \Exception("Error recepción: " . socket_strerror(socket_last_error()));
    }
        
        return $this->parseAttendanceData($response);
    }

    private function parseAttendanceData(string $data): array
    {
        $records = [];
        $offset = 14; // K30 header size
        
        while ($offset < strlen($data)) {
            $record = [
                'user_id' => unpack('n', substr($data, $offset, 2))[1],
                'timestamp' => strtotime(substr($data, $offset + 2, 4)),
                'punch_type' => ord($data[$offset + 6])
            ];
            $records[] = $record;
            $offset += 8; // K30 record size
        }
        
        return $records;
    }

    public function dissconnect(): void {
        if($this->socket) {
            socket_close($this->socket);
        }
    }
}
