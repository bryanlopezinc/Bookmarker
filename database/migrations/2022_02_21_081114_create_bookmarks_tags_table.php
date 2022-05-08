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
        Schema::create('bookmarks_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bookmark_id')->constrained('bookmarks')->cascadeOnDelete();
            $table->unsignedBigInteger('tag_id');
            $table->unique(['tag_id', 'bookmark_id']);
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
        Schema::dropIfExists('bookmarks_tags');
    }
};
