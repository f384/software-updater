<?php

namespace App\Http\Controllers;

use App\Jobs\RunDeploymentJob;
use App\Models\Deployment;
use App\Models\DeploymentTarget;
use Illuminate\Http\Request;    
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;

class DeploymentController extends Controller
{
    public function create()
    {
        return Inertia::render('Deployments/Create');
    }
    public function store(Request $request)
    {
        $request->validate([
            'installer_rel_path' => 'required|string',
            'uninstall_first'    => 'boolean',
            'install_args'       => 'nullable|string',
            'uninstall_args'     => 'nullable|string',
            'hosts'              => 'required|string',
        ]);

        $hosts = json_decode($request->input('hosts'), true) ?: [];
        $root  = rtrim(Setting::get('installer_root', ''), "\\/");

        $rel   = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $request->input('installer_rel_path'));
        $full  = $root . DIRECTORY_SEPARATOR . ltrim($rel, "\\/");

        $deployment = Deployment::create([
            'user_id'                => Auth::id(),
            'installer_original_name'=> basename($full),
            'installer_stored_path'  => $full,
            'uninstall_first'        => $request->boolean('uninstall_first'),
            'options'                => [
                'install_args'   => $request->input('install_args', ''),
                'uninstall_args' => $request->input('uninstall_args', ''),
            ],
            'total_targets'          => count($hosts),
            'status'                 => 'planned',
        ]);

        foreach ($hosts as $h) {
            DeploymentTarget::create([
                'deployment_id' => $deployment->id,
                'ip'            => $h['ip'] ?? null,
                'hostname'      => $h['hostname'] ?? $h['ip'] ?? 'unknown',
                'username'      => $h['username'] ?? null,
                'password'      => $h['password'] ?? null,
                'status'        => 'pending',
            ]);
        }

        // 🚀 Trigger the job here
        RunDeploymentJob::dispatch($deployment);

        return redirect()->route('dashboard')->with('success', 'Deployment created and started!');
    }

    public function browse(Request $request)
    {
        $root = (string) Setting::get('installer_root', '');
        if ($root === '') {
            return response()->json([
                'items' => [],
                'current' => '',
                'root' => '',
                'message' => 'Installer root is not set. Go to Settings and set a shared folder (e.g. \\\\FilipPC\\Installers).',
            ], 422);
        }

        $ds   = DIRECTORY_SEPARATOR;
        $root = rtrim(str_replace(['/', '\\'], $ds, $root), $ds);

        $rel = trim((string) $request->query('path', ''), "\\/");
        if (str_contains($rel, '..')) {
            $rel = '';
        }

        $current = $root . ($rel !== '' ? $ds . str_replace(['/', '\\'], $ds, $rel) : '');

        if (!is_dir($current)) {
            if ($rel !== '' && is_dir($root)) {
                $current = $root;
                $rel = '';
            } else {
                return response()->json([
                    'items' => [],
                    'current' => $rel,
                    'root' => $root,
                    'message' => "Path not found or not accessible: {$current}",
                ], 404);
            }
        }

        $items = [];
        foreach (glob($current . $ds . '*', GLOB_NOSORT) ?: [] as $entry) {
            if (is_dir($entry)) {
                $items[] = [
                    'type' => 'dir',
                    'name' => basename($entry),
                    'path' => ltrim(str_replace($root . $ds, '', $entry), '\\/'),
                ];
            } elseif (is_file($entry)) {
                $size = @filesize($entry);
                $items[] = [
                    'type' => 'file',
                    'name' => basename($entry),
                    'path' => ltrim(str_replace($root . $ds, '', $entry), '\\/'),
                    'size' => $size !== false ? round($size / 1048576, 2) . ' MB' : null,
                ];
            }
        }

        usort($items, function ($a, $b) {
            if ($a['type'] !== $b['type']) return $a['type'] === 'dir' ? -1 : 1;
            return strcasecmp($a['name'], $b['name']);
        });

        return response()->json([
            'items'   => $items,
            'current' => $rel,     
            'root'    => $root,   
        ]);
    }

    public function show(Deployment $deployment)
    {
        $deployment->load('targets');

        return Inertia::render('Deployments/Show', [
            'deployment' => $deployment,
        ]);
    }
}
