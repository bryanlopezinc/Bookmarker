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
        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained('tags');
            $table->foreignId('taggable_id');
            $table->unsignedTinyInteger('taggable_type');
            $table->foreignId('tagged_by_id')->index();
            $table->unique(['tag_id', 'taggable_id', 'taggable_type']);
        });

        DB::unprepared(<<<SQL
            CREATE TRIGGER delete_bookmark_tag_record_on_bookmark_delete
            BEFORE DELETE ON bookmarks FOR EACH ROW
            DELETE FROM taggables t  WHERE t.taggable_id = OLD.id AND t.taggable_type = 4
        SQL);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('taggables');
    }
};
