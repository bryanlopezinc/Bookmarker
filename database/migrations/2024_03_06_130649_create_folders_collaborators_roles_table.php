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
        Schema::create('folders_collaborators_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->index();
            $table->foreignId('collaborator_id');
            $table->unique(['collaborator_id', 'role_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folders_collaborators_roles');
    }
};
