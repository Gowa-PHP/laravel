<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $table = config('gowa.table_names.instances', 'gowa_instances');
        $teams = config('gowa.teams.enabled', false);
        $teamFk = config('gowa.teams.foreign_key', 'team_id');

        Schema::create($table, function (Blueprint $t) use ($teams, $teamFk) {
            $t->id();

            if ($teams) {
                $t->unsignedBigInteger($teamFk)->index();
            }

            $t->string('name');
            $t->string('device_id')->unique();
            $t->string('status', 30)->default('created');
            $t->string('phone_number', 30)->nullable();
            $t->string('webhook_secret')->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('connected_at')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('gowa.table_names.instances', 'gowa_instances'));
    }
};
