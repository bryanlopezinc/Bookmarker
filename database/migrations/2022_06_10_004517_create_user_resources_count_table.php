<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::create('users_resources_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('count');
            $table->unsignedTinyInteger('type');
            $table->unique(['type', 'user_id']);
            $table->timestamps();
        });

        DB::unprepared(<<<SQL
            CREATE TRIGGER decrement_user_resources_count
            AFTER DELETE ON bookmarks FOR EACH ROW
            BEGIN
                UPDATE  users_resources_counts urc
                SET urc.count = urc.count - 1
                WHERE  urc.type = 3 AND urc.user_id = OLD.user_id;
            END;

            -- Decrement favorites count before favorites is cascade deleted
            CREATE TRIGGER decrement_user_favourites_count
            BEFORE DELETE ON bookmarks FOR EACH ROW
               IF( EXISTS (SELECT * FROM favourites WHERE user_id = OLD.user_id AND bookmark_id = OLD.id) ) THEN
                    UPDATE  users_resources_counts urc
                    SET urc.count = urc.count - 1
                    WHERE  urc.type = 4 AND urc.user_id = OLD.user_id;
                END IF;

            CREATE TRIGGER decrement_user_folders_count
            AFTER DELETE ON folders FOR EACH ROW
            BEGIN
                UPDATE  users_resources_counts urc
                SET urc.count = urc.count - 1
                WHERE  urc.type = 5 AND urc.user_id = OLD.user_id;
            END;
        SQL);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users_resources_counts');
    }
};
