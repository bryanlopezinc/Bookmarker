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
        Schema::create('folders_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->index();
            $table->foreignId('collaborator_id');
            $table->foreignId('invited_by');
            $table->unique(['folder_id', 'collaborator_id']);
            $table->timestamp('joined_at');
        });
    }
};
