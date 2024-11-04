<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('tacos', function (Blueprint $table) {
            $table->foreignId('slack_event_id')->constrained('slack_events')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('tacos', function (Blueprint $table) {
            $table->dropForeign(['slack_event_id']);
            $table->dropColumn('slack_event_id');
        });
    }
};
