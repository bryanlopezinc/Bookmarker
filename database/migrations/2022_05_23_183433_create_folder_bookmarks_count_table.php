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
        Schema::create('folders_bookmarks_count', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->unique()->constrained('folders')->cascadeOnDelete();
            $table->unsignedBigInteger('count');
            $table->timestamps();
        });

        DB::unprepared(<<<SQL
            CREATE TRIGGER decrement_folders_bookmarks_count
            AFTER DELETE ON folders_bookmarks FOR EACH ROW
            UPDATE  folders_bookmarks_count fbc
            SET fbc.count = fbc.count - 1
            WHERE  fbc.folder_id =  OLD.folder_id;
        SQL);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('folders_bookmarks_count');
    }
};
