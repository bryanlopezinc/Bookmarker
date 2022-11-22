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
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_public')->index();
            $table->foreignId('user_id')->index();
            $table->string('description', 150)->nullable();
            $table->string('name', 50)->index();
            $table->timestamps();
            $table->index('updated_at');
            $table->json('settings');
        });

        $json =  file_get_contents(base_path('database/JsonSchema/folder_settings_1.0.0.json'));

        DB::unprepared(
            <<<SQL
                ALTER TABLE folders ADD CONSTRAINT validate_folder_setting CHECK(JSON_SCHEMA_VALID('$json', settings))
            SQL
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('folders');
    }
};
