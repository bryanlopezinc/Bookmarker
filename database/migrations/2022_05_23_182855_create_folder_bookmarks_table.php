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
        Schema::create('folders_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bookmark_id')->constrained('bookmarks');
            $table->foreignId('folder_id')->constrained('folders');
            $table->unique(['bookmark_id', 'folder_id']);
            $table->timestamp('created_at')->useCurrent();
        });

        //Favor trigger over cascade delete to enable other triggers fire
        DB::unprepared(<<<SQL
            CREATE TRIGGER delete_folders_bookmark_on_bookmark_delete
            BEFORE DELETE ON bookmarks FOR EACH ROW
            DELETE FROM folders_bookmarks fb WHERE fb.bookmark_id = OLD.id
        SQL);
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
