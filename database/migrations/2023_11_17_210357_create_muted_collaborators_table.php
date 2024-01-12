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
        Schema::create('folders_muted_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id');
            $table->foreignId('user_id');
            $table->foreignId('muted_by');
            $table->unique(['folder_id', 'user_id', 'muted_by']);
            $table->timestamp('muted_at');
            $table->timestamp('muted_until')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('muted_collaborators');
    }
};
