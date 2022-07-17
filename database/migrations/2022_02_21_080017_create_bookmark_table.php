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
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->boolean('has_custom_title');
            $table->string('url');
            $table->string('resolved_url');
            $table->timestamp('resolved_at')->nullable();
            $table->string('url_canonical');
            $table->string('url_canonical_hash', 20);
            $table->string('description', 200)->nullable();
            $table->boolean('description_set_by_user');
            $table->unsignedBigInteger('site_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->text('preview_image_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bookmarks');
    }
};
