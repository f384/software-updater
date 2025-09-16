<?php

namespace App\Jobs;

use App\Models\Deployment;
use App\Models\DeploymentTarget;
use App\Events\DeploymentTargetUpdated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;

class RunDeploymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $deployment;

    public function __construct(Deployment $deployment)
    {
        $this->deployment = $deployment;
    }

    public function handle()
    {
        $deployment = Deployment::with('targets')->find($this->deployment->id);
        $deployment->update(['status' => 'running']);

        foreach ($deployment->targets as $target) {
            $target->update(['status' => 'running']);
            broadcast(new DeploymentTargetUpdated($target)); // 🔔 broadcast status change

            $installer   = $deployment->installer_stored_path;
            $installArgs = $deployment->options['install_args'] ?? '';
            $username    = $target->username;
            $password    = $target->password; // auto-decrypted in model
            $ip          = $target->ip;

            try {
                // Example command with PsExec
                $cmd = [
                    'C:\\tools\\psexec.exe',
                    "\\\\$ip",
                    '-u', $username,
                    '-p', $password,
                    $installer,
                ];
                if (!empty($installArgs)) {
                    $cmd[] = $installArgs;
                }

                $process = new Process($cmd);
                $process->setTimeout(900); // 15min
                $process->run();

                $target->update([
                    'status'  => $process->isSuccessful() ? 'success' : 'failed',
                    'message' => $process->getOutput() ?: $process->getErrorOutput(),
                ]);

            } catch (\Throwable $e) {
                $target->update([
                    'status'  => 'failed',
                    'message' => $e->getMessage(),
                ]);
            }

            broadcast(new DeploymentTargetUpdated($target)); // 🔔 broadcast after finish
        }

        $deployment->update(['status' => 'completed']);
    }
}
