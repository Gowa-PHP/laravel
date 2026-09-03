<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $table = config('gowa.table_names.webhook_calls', 'gowa_webhook_calls');
        $instanceTable = config('gowa.table_names.instances', 'gowa_instances');
        $teams = config('gowa.teams.enabled', false);
        $teamFk = config('gowa.teams.foreign_key', 'team_id');

        Schema::create($table, function (Blueprint $t) use ($instanceTable, $teams, $teamFk) {
            $t->id();

            if ($teams) {
                $t->unsignedBigInteger($teamFk)->nullable()->index();
            }

            $t->foreignId('instance_id')->nullable()->constrained($instanceTable)->nullOnDelete();
            $t->string('device_id')->index();
            $t->string('event', 50)->index();
            $t->string('url')->nullable();
            $t->json('headers')->nullable();
            $t->json('payload')->nullable();
            $t->text('exception')->nullable();
            $t->boolean('processed')->default(true);
            $t->timestamps();

            $t->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('gowa.table_names.webhook_calls', 'gowa_webhook_calls'));
    }
};
