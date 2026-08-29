<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('gowa.table_names.conversations', 'gowa_conversations');
        $instanceTable = config('gowa.table_names.instances', 'gowa_instances');
        $teams = config('gowa.teams.enabled', false);
        $teamFk = config('gowa.teams.foreign_key', 'team_id');

        Schema::create($table, function (Blueprint $t) use ($instanceTable, $teams, $teamFk) {
            $t->id();

            if ($teams) {
                $t->unsignedBigInteger($teamFk)->index();
            }

            $t->foreignId('instance_id')->constrained($instanceTable)->cascadeOnDelete();
            $t->string('contact_jid');
            $t->string('contact_name')->nullable();
            $t->string('contact_phone', 30)->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('last_message_at')->nullable();
            $t->timestamps();

            $t->unique(['instance_id', 'contact_jid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('gowa.table_names.conversations', 'gowa_conversations'));
    }
};
