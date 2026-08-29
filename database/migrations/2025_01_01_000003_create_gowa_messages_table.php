<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('gowa.table_names.messages', 'gowa_messages');
        $instanceTable = config('gowa.table_names.instances', 'gowa_instances');
        $convTable = config('gowa.table_names.conversations', 'gowa_conversations');
        $teams = config('gowa.teams.enabled', false);
        $teamFk = config('gowa.teams.foreign_key', 'team_id');

        Schema::create($table, function (Blueprint $t) use ($instanceTable, $convTable, $teams, $teamFk) {
            $t->id();

            if ($teams) {
                $t->unsignedBigInteger($teamFk)->index();
            }

            $t->foreignId('instance_id')->constrained($instanceTable)->cascadeOnDelete();
            $t->foreignId('conversation_id')->nullable()->constrained($convTable)->nullOnDelete();
            $t->string('message_id')->index();
            $t->string('direction', 10);
            $t->string('status', 20)->default('pending');
            $t->string('type', 20)->default('text');
            $t->text('body')->nullable();
            $t->string('media_url')->nullable();
            $t->string('media_mime', 100)->nullable();
            $t->string('reply_to')->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('gowa.table_names.messages', 'gowa_messages'));
    }
};
