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
use Illuminate\Support\Facades\DB;
use Lemric\BatchProcessing\Repository\PdoJobRepositorySchema;

/*
 * PostgreSQL-specific batch processing schema.
 */
return new class extends Migration {
    public function down(): void
    {
        if (!in_array(DB::connection()->getDriverName(), ['pgsql', 'postgres', 'postgresql'], true)) {
            return;
        }
        foreach (PdoJobRepositorySchema::dropSqlForPlatform('pgsql', $this->prefix()) as $sql) {
            DB::statement($sql);
        }
    }

    public function up(): void
    {
        if (!in_array(DB::connection()->getDriverName(), ['pgsql', 'postgres', 'postgresql'], true)) {
            return;
        }
        foreach (PdoJobRepositorySchema::sqlForPlatform('pgsql', $this->prefix()) as $sql) {
            DB::statement($sql);
        }
    }

    private function prefix(): string
    {
        if (function_exists('config')) {
            /** @var string $configured */
            $configured = config('batch-processing.table_prefix', 'batch_');

            return '' !== $configured ? $configured : 'batch_';
        }

        return 'batch_';
    }
};
