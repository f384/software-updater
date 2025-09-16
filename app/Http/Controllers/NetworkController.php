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
                ? $this->scanFull($request)
                : $this->scanQuick($request);
        }


        /** QUICK: parse "arp -a" and resolve hostnames */
    private function scanQuick(Request $request)
    {
        $systemRoot = getenv('SystemRoot') ?: 'C:\\Windows';

        // Prefer absolute paths first
        $candidates = [
            [$systemRoot . '\\System32\\arp.exe', '-a'],
            [$systemRoot . '\\Sysnative\\arp.exe', '-a'],
            ['arp', '-a'], // fallback if PATH works
        ];

        $out = null;
        foreach ($candidates as $cmd) {
            $p = new Process(array_merge(['cmd','/C'], $cmd));
            $p->setTimeout(5);
            $p->run();

            if ($p->isSuccessful()) {
                $tmp = trim($p->getOutput());
                if ($tmp !== '' && stripos($tmp, 'is not recognized') === false) {
                    $out = $tmp;
                    break;
                }
            }
        }
        
        // Fallback: netsh neighbors (if arp not available or empty)
        if ($out === null || $out === '') {
            $p = new Process(['cmd','/C','netsh','interface','ipv4','show','neighbors']);
            $p->setTimeout(5);
            $p->run();
            if (!$p->isSuccessful() || trim($p->getOutput()) === '') {
                return response()->json([
                    'error'   => 'Scan failed',
                    'details' => trim($p->getErrorOutput() . $p->getOutput()) ?: 'Neither arp nor netsh were available.',
                ], 500);
            }
            $out = trim($p->getOutput());
        }
        
        // Extract IPv4s
        preg_match_all('/\b(\d{1,3}(?:\.\d{1,3}){3})\b/', $out, $m);
        $ips = array_unique($m[1] ?? []);
        
        // Decide whether to resolve hostnames
        $resolve = $request->query('resolve', 0) ? true : false;
        
        $subnet = $request->query('subnet', null);

        $results = [];
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) continue;
            if (preg_match('/^(127\.|169\.254\.|224\.|239\.|255\.)/', $ip)) continue;

            $host = null;
            if ($resolve) {
                if ($subnet === null || str_starts_with($ip, $subnet)) {
                    $tmpHost = @gethostbyaddr($ip);
                    if ($tmpHost && $tmpHost !== $ip) {
                        $host = $tmpHost;
                    }
                }
            }

            $results[] = [
                'ip'       => $ip,
                'hostname' => $host,
            ];
        }
        
        usort($results, fn($a, $b) => ip2long($a['ip']) <=> ip2long($b['ip']));
        return response()->json($results);
    }



        /** FULL: find local /24, ping 1..254 and resolve */
    private function scanFull(Request $request)
    {
        // extend execution time (5 minutes max)
        set_time_limit(300);

        $ip = $this->getLocalIPv4();
        if (!$ip) {
            return response()->json(['error' => 'No IPv4 address found.'], 500);
        }

        $parts = explode('.', $ip);
        if (count($parts) !== 4) {
            return response()->json(['error' => 'Invalid IPv4 address.'], 500);
        }
        $prefix = "{$parts[0]}.{$parts[1]}.{$parts[2]}";

        // params
        $resolve = $request->boolean('resolve', false);
        $subnet  = $request->query('subnet', null);

        $alive = [];
        for ($i = 1; $i <= 254; $i++) {
            $target = "$prefix.$i";

            // optional subnet filter
            if ($subnet && strpos($target, $subnet) !== 0) {
                continue;
            }

            $p = new Process(['cmd', '/C', 'ping', '-n', '1', '-w', '80', $target]); // 80ms timeout per host
            $p->setTimeout(2);
            $p->run();

            $out = $p->getOutput();
            if (strpos($out, 'TTL=') !== false) {
                $host = null;
                if ($resolve) {
                    $tmpHost = @gethostbyaddr($target);
                    if ($tmpHost && $tmpHost !== $target) {
                        $host = $tmpHost;
                    }
                }
                $alive[] = [
                    'ip'       => $target,
                    'hostname' => $host,
                ];
            }
        }

        usort($alive, fn($a, $b) => ip2long($a['ip']) <=> ip2long($b['ip']));
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

        private function debugRun(Process $p)
        {
            try {
                $p->mustRun();
                return [
                    'success' => true,
                    'output'  => $p->getOutput(),
                    'error'   => $p->getErrorOutput(),
                    'command' => $p->getCommandLine(),
                ];
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error'   => $p->getErrorOutput(),
                    'output'  => $p->getOutput(),
                    'command' => $p->getCommandLine(),
                    'exception' => $e->getMessage(),
                ];
            }
        }

    
}
