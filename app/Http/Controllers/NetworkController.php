<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
class NetworkController extends Controller
{
        public function scan(Request $request)
        {
            $mode = $request->query('mode', 'quick'); // quick | full

            if (stripos(PHP_OS_FAMILY, 'Windows') === false) {
                return response()->json(['error' => 'This scan implementation is for Windows.'], 422);
            }

            return $mode === 'full'
                ? $this->scanFull()
                : $this->scanQuick();
        }

        /** QUICK: parse "arp -a" and resolve hostnames */
        private function scanQuick()
        {
            $systemRoot = getenv('SystemRoot') ?: 'C:\\Windows';

            // Try several ways to call ARP; if all fail, fall back to netsh
            $candidates = [
                // 64-bit System32
                [$systemRoot . '\\System32\\arp.exe', '-a'],
                // 32-bit PHP accessing 64-bit System32 via Sysnative
                [$systemRoot . '\\Sysnative\\arp.exe', '-a'],
                // Plain command (in case PATH is correct)
                ['arp', '-a'],
            ];

            $out = null;
            foreach ($candidates as $cmd) {
                $p = new Process(array_merge(['cmd','/C'], $cmd));
                $p->setTimeout(5);
                $p->run();
                if ($p->isSuccessful()) {
                    $out = $p->getOutput();
                    if (trim($out) !== '' && stripos($out, 'is not recognized') === false) {
                        break;
                    }
                }
            }

            // Fallback: netsh neighbors (works without arp)
            if ($out === null || stripos($out, 'is not recognized') !== false || trim($out) === '') {
                $p = new Process(['cmd','/C','netsh','interface','ipv4','show','neighbors']);
                $p->setTimeout(5);
                $p->run();
                if (!$p->isSuccessful()) {
                    return response()->json([
                        'error'   => 'Scan failed',
                        'details' => trim($p->getErrorOutput() . $p->getOutput()) ?: 'Neither arp nor netsh were available.',
                    ], 500);
                }
                $out = $p->getOutput();
            }

            // Extract IPv4s
            preg_match_all('/\b(\d{1,3}(?:\.\d{1,3}){3})\b/', $out, $m);
            $ips = array_unique($m[1] ?? []);

            $results = [];
            foreach ($ips as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP)) continue;
                if (preg_match('/^(127\.|169\.254\.)/', $ip)) continue;
                $host = @gethostbyaddr($ip);
                $results[] = [
                    'ip'       => $ip,
                    'hostname' => ($host && $host !== $ip) ? $host : null,
                ];
            }

            usort($results, fn($a,$b) => ip2long($a['ip']) <=> ip2long($b['ip']));
            return response()->json($results);
        }

        /** FULL: find local /24, ping 1..254 and resolve */
        private function scanFull()
        {
            $ip = $this->getLocalIPv4();
            if (!$ip) {
                return response()->json(['error' => 'No IPv4 address found.'], 500);
            }

            $parts = explode('.', $ip);
            if (count($parts) !== 4) {
                return response()->json(['error' => 'Invalid IPv4 address.'], 500);
            }
            $prefix = "{$parts[0]}.{$parts[1]}.{$parts[2]}";

            $alive = [];
            // Simple sequential ping; tweak -w (timeout ms) if needed
            for ($i = 1; $i <= 254; $i++) {
                $target = "$prefix.$i";
                $p = new Process(['cmd', '/C', 'ping', '-n', '1', '-w', '80', $target]); // 80ms timeout
                $p->setTimeout(2);
                $p->run();

                $out = $p->getOutput();
                if (strpos($out, 'TTL=') !== false) {
                    $host = @gethostbyaddr($target);
                    $alive[] = [
                        'ip'       => $target,
                        'hostname' => ($host && $host !== $target) ? $host : null,
                    ];
                }
            }

            usort($alive, fn($a,$b) => ip2long($a['ip']) <=> ip2long($b['ip']));
            return response()->json($alive);
        }

        /** Get a non-APIPA local IPv4 (Windows) */
        private function getLocalIPv4(): ?string
        {
            $p = new Process(['cmd', '/C', 'ipconfig']);
            $p->setTimeout(3);
            $p->run();
            $out = $p->getOutput();

            if (preg_match_all('/IPv4[^:]*:\s*([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/i', $out, $m)) {
                foreach ($m[1] as $ip) {
                    if (strpos($ip, '169.254.') === 0) continue; // APIPA
                    if ($ip === '127.0.0.1') continue;
                    return $ip;
                }
            }

            $ip = gethostbyname(gethostname());
            if (filter_var($ip, FILTER_VALIDATE_IP) && strpos($ip, '127.') !== 0) {
                return $ip;
            }
            return null;
        }
    
}
