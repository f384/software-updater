<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('installer_original_name');   // original filename
            $table->string('installer_stored_path');     // stored in storage/app/installers
            $table->boolean('uninstall_first')->default(true);
            $table->json('options')->nullable();         // optional args (install/uninstall flags)
            $table->unsignedInteger('total_targets')->default(0);
            $table->enum('status', ['planned', 'running', 'completed', 'failed'])->default('planned');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('deployment_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deployment_id')->constrained()->cascadeOnDelete();
            $table->string('hostname');    // PC name or IP
            $table->enum('status', ['pending', 'running', 'success', 'failed', 'skipped'])->default('pending');
            $table->unsignedTinyInteger('progress')->default(0); // 0–100 %
            $table->text('log')->nullable(); // error/success logs
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['deployment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_targets');
        Schema::dropIfExists('deployments');
    }
};
