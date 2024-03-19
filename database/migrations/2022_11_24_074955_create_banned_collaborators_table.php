<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('banned_collaborators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id');
            $table->foreignId('user_id');
            $table->unique(['folder_id', 'user_id'], 'unique_banned_users');
            $table->timestamp('banned_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('banned_collaborators');
    }
};
