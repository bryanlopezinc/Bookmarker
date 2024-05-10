<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('suspended_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id');
            $table->foreignId('collaborator_id');
            $table->foreignId('suspended_by');
            $table->unsignedTinyInteger('duration_in_hours')->nullable();
            $table->timestamp('suspended_until')->nullable();
            $table->timestamp('suspended_at');
            $table->unique(['folder_id', 'collaborator_id']);
        });
    }
};
