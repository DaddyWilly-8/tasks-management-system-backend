<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('title', 255);
            $table->date('event_date');
            $table->string('type', 100);
            $table->enum('created_by', ['user', 'system'])->default('user');
            $table->string('background_color', 10)->default('#455A64');
            $table->string('border_color', 10)->default('#455A64');
            $table->string('entity_model', 100)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('details_url', 255)->nullable();
            $table->enum('status', ['active', 'cancelled'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('calendar_events');
    }
};
