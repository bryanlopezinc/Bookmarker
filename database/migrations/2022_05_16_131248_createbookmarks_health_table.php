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
        Schema::create('bookmarks_health', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bookmark_id')->unique()->constrained('bookmarks')->cascadeOnDelete();
            $table->boolean('is_healthy');
            $table->date('last_checked')->index();
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
        Schema::dropIfExists('bookmarks_health');
    }
};
