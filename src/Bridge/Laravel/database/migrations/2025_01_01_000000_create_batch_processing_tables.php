<?php

/**
 * This file is part of the Lemric package.
 * (c) Lemric
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Dominik Labudzinski <dominik@labudzinski.com>
 */
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Creates the batch processing metadata tables required by {@see \Lemric\BatchProcessing\Repository\PdoJobRepository}.
 *
 * Publish with: php artisan vendor:publish --tag=batch-processing-migrations
 */
return new class extends Migration {
    public function down(): void
    {
        $p = $this->prefix();

        Schema::dropIfExists($p.'job_execution_context');
        Schema::dropIfExists($p.'step_execution_context');
        Schema::dropIfExists($p.'step_execution');
        Schema::dropIfExists($p.'job_execution_params');
        Schema::dropIfExists($p.'job_execution');
        Schema::dropIfExists($p.'job_instance');
    }

    public function up(): void
    {
        $p = $this->prefix();

        Schema::create($p.'job_instance', function (Blueprint $table) {
            $table->bigIncrements('job_instance_id');
            $table->bigInteger('version')->default(0);
            $table->string('job_name', 100);
            $table->string('job_key', 2500);
            $table->unique(['job_name', 'job_key'], 'job_inst_un');
            $table->index('job_name', 'idx_job_inst_name');
        });

        Schema::create($p.'job_execution', function (Blueprint $table) use ($p) {
            $table->bigIncrements('job_execution_id');
            $table->bigInteger('version')->default(0);
            $table->unsignedBigInteger('job_instance_id');
            $table->dateTime('create_time', 6);
            $table->dateTime('start_time', 6)->nullable();
            $table->dateTime('end_time', 6)->nullable();
            $table->string('status', 10);
            $table->string('exit_code', 2500);
            $table->text('exit_message')->nullable();
            $table->dateTime('last_updated', 6)->nullable();
            $table->foreign('job_instance_id')->references('job_instance_id')->on($p.'job_instance');
            $table->index('status', 'idx_job_exec_status');
        });

        Schema::create($p.'job_execution_params', function (Blueprint $table) use ($p) {
            $table->unsignedBigInteger('job_execution_id');
            $table->string('param_name', 100);
            $table->string('param_type', 100);
            $table->string('param_value', 2500)->nullable();
            $table->char('identifying', 1);
            $table->foreign('job_execution_id')->references('job_execution_id')->on($p.'job_execution');
        });

        Schema::create($p.'step_execution', function (Blueprint $table) use ($p) {
            $table->bigIncrements('step_execution_id');
            $table->bigInteger('version')->default(0);
            $table->string('step_name', 100);
            $table->unsignedBigInteger('job_execution_id');
            $table->dateTime('create_time', 6);
            $table->dateTime('start_time', 6)->nullable();
            $table->dateTime('end_time', 6)->nullable();
            $table->string('status', 10);
            $table->bigInteger('commit_count')->nullable();
            $table->bigInteger('read_count')->nullable();
            $table->bigInteger('filter_count')->nullable();
            $table->bigInteger('write_count')->nullable();
            $table->bigInteger('read_skip_count')->nullable();
            $table->bigInteger('write_skip_count')->nullable();
            $table->bigInteger('process_skip_count')->nullable();
            $table->bigInteger('rollback_count')->nullable();
            $table->string('exit_code', 2500);
            $table->text('exit_message')->nullable();
            $table->dateTime('last_updated', 6)->nullable();
            $table->foreign('job_execution_id')->references('job_execution_id')->on($p.'job_execution');
            $table->index('job_execution_id', 'idx_step_exec_job');
            $table->index('step_name', 'idx_step_exec_name');
        });

        Schema::create($p.'step_execution_context', function (Blueprint $table) use ($p) {
            $table->unsignedBigInteger('step_execution_id')->primary();
            $table->string('short_context', 2500);
            $table->longText('serialized_context')->nullable();
            $table->foreign('step_execution_id')->references('step_execution_id')->on($p.'step_execution');
        });

        Schema::create($p.'job_execution_context', function (Blueprint $table) use ($p) {
            $table->unsignedBigInteger('job_execution_id')->primary();
            $table->string('short_context', 2500);
            $table->longText('serialized_context')->nullable();
            $table->foreign('job_execution_id')->references('job_execution_id')->on($p.'job_execution');
        });
    }

    private function prefix(): string
    {
        // @phpstan-ignore-next-line
        return config('batch_processing.table_prefix', 'batch_');
    }
};
