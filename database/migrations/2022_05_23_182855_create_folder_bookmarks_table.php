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
        Schema::create('folders_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->enum('visibility', ['private', 'public'])->index();
            $table->foreignId('bookmark_id')->index('bookmark_id_index');
            $table->foreignId('folder_id')->index('folder_id_index');
            $table->unique(['bookmark_id', 'folder_id']);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('folders_bookmarks');
    }
};
