<?php
namespace App\Services;

class ZKTecoService
{

    private const CMD_STARTING = 0x5050;
    private const CMD_CONNECT = 0x0101;      // Específico para K30
    private const CMD_EXIT = 0x0102;
    private const CMD_ATTLOG_RRQ = 0x0107;   // Específico para K30
    private const CMD_AUTH = 0x0103;         // Específico para K30

   private string $ip;
   private int $port;
   private $socket;
   private int $key;
   private int $sessionId = 0;
   private int $replyId = 0;
   private const MAX_CHUNK_SIZE = 1024;
   private const HEADER_SIZE = 8;

   public function __construct(string $ip, int $port, int $key = 0)
   {
       $this->ip = $ip;
       $this->port = $port;
       $this->key = $key;
   }

   public function getAttendance(): array
   {
       $command = $this->createPacket(self::CMD_ATTLOG_RRQ);
       if (!$this->sendCommand($command, true)) {
           throw new \Exception("Failed to get attendance data");
       }

       return $this->parseAttendanceData($this->readResponse());
   }

   private function createPacket(int $command, string $data = ''): string 
   {
       // El K30 usa un formato específico: 
       // 2 bytes - header fijo (0x5050)
       // 2 bytes - comando
       // 2 bytes - checksum
       // 2 bytes - session id
       // N bytes - datos (opcional)
       
       $checksum = 0;
       $session = ($this->sessionId === 0) ? 0xFFFF : $this->sessionId;
       
       $buf = pack('SSSS', 
           self::CMD_STARTING,
           $command,
           $checksum,
           $session
       );
       
       if (!empty($data)) {
           $buf .= $data;
           // Calcular checksum incluyendo los datos
           $checksum = $this->calculateChecksum($buf);
           // Reemplazar el checksum en el paquete
           $buf = substr_replace($buf, pack('S', $checksum), 4, 2);
       }
       
       return $buf;
   }

   private function calculateChecksum(string $data): int
   {
       $checksum = 0;
       for ($i = 0; $i < strlen($data); $i++) {
           $checksum += ord($data[$i]);
       }
       return $checksum & 0xFFFF;
   }

   private function sendCommand(string $command, bool $getResponse = false): bool
   {
       try {
           echo "Sending command...\n";
           echo "Command (hex): " . bin2hex($command) . "\n";
           
           $sent = socket_write($this->socket, $command, strlen($command));
           if ($sent === false) {
               throw new \Exception("Failed to send command: " . socket_strerror(socket_last_error()));
           }
           echo "Command sent successfully ({$sent} bytes)\n";

           if (!$getResponse) {
               return true;
           }

           // Esperar y leer la respuesta
           echo "Waiting for response...\n";
           $response = $this->readResponse();
           
           if (empty($response)) {
               throw new \Exception("No response received");
           }

           echo "Response received: " . bin2hex($response) . "\n";
           
           // Analizar la respuesta
           $header = unpack('Scommand/Sreply/Schecksum/Ssession', $response);
           $this->sessionId = $header['session'];
           
           echo "Session ID: " . $this->sessionId . "\n";
           return true;
           
       } catch (\Exception $e) {
           echo "Command error: " . $e->getMessage() . "\n";
           throw $e;
       }
   }

   private function readResponse(): string
    {
        $response = '';
        $attempts = 0;
        $maxAttempts = 3;

        while ($attempts < $maxAttempts && strlen($response) < self::HEADER_SIZE) {
            $buffer = socket_read($this->socket, self::MAX_CHUNK_SIZE);
            if ($buffer !== false && !empty($buffer)) {
                $response .= $buffer;
                echo "Received chunk: " . bin2hex($buffer) . "\n";
            }
            $attempts++;
            usleep(100000); // 100ms delay between attempts
        }

        return $response;
    }
   public function connect(): bool
    {
        try {
            echo "Creating socket...\n";
            $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            
            if (!$this->socket) {
                throw new \Exception("Error creating socket: " . socket_strerror(socket_last_error()));
            }
            
            echo "Setting socket options...\n";
            socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ["sec" => 3, "usec" => 0]);
            socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ["sec" => 3, "usec" => 0]);
            
            echo "Connecting to {$this->ip}:{$this->port}...\n";
            if (!@socket_connect($this->socket, $this->ip, $this->port)) {
                throw new \Exception("Failed to connect: " . socket_strerror(socket_last_error()));
            }
            echo "TCP connection established\n";

            // Enviar comando de conexión inicial
            $command = $this->createPacket(self::CMD_CONNECT);
            return $this->sendCommand($command, true);
        } catch (\Exception $e) {
            echo "Connection error: " . $e->getMessage() . "\n";
            throw $e;
        }
    }


    public function authenticate(): bool 
    {
        if ($this->key === 0) {
            return true; // No authentication needed
        }

        $data = pack('L', $this->key);
        $command = $this->createPacket(self::CMD_AUTH, $data);
        return $this->sendCommand($command, true);
    }

    private function parseAttendanceData(string $data): array
    {
        $attendance = [];
        $position = self::HEADER_SIZE; // Skip header
        
        while ($position < strlen($data)) {
            if ($position + 40 > strlen($data)) break;
            
            $record = unpack('Lid/a32timestamp/Cstatus', substr($data, $position, 37));
            
            $attendance[] = [
                'user_id' => $record['id'],
                'timestamp' => strtotime(trim($record['timestamp'])),
                'status' => $record['status']
            ];
            
            $position += 40;
        }

        return $attendance;
    }

    public function close(): void
{
    if ($this->socket) {
        socket_shutdown($this->socket); // Agregamos shutdown para TCP
        socket_close($this->socket);
    }
}

}