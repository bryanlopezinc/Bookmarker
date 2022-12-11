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
        Schema::create('folders_collaborators_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->index();
            $table->foreignId('user_id')->index();
            $table->foreignId('permission_id')->constrained('folders_permissions');
            $table->unique(['folder_id', 'user_id', 'permission_id'], 'unique_permission');
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('folders_collaborators_permissions');
    }
};
