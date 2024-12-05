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
        Schema::create('zoom_meeting_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained('zoom_meetings')->onDelete('cascade'); //ZoomMeetings
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // User
            $table->text('text'); // textarea for notes
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zoom_meeting_notes');
    }
};
