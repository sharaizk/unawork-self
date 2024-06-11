<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_pods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('pod_id');
            $table->string('permission');
            $table->string('status');
            $table->string('reason')->nullable();
            $table->timestamps();

            // Додаємо зовнішні ключі
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('pod_id')->references('id')->on('pods')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_pods', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['pod_id']);
        });
        Schema::dropIfExists('user_pods');
    }
};
