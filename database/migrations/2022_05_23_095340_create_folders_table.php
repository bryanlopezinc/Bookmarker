<?php

declare(strict_types=1);

use App\Contracts\IdGeneratorInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', IdGeneratorInterface::LENGTH)->unique();
            $table->unsignedTinyInteger('visibility')->index();
            $table->foreignId('user_id')->index();
            $table->string('description', 150)->nullable();
            $table->string('name', 50)->index();
            $table->json('settings');
            $table->text('icon_path')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
            $table->index('updated_at');
        });

        DB::statement(<<<SQL
            ALTER TABLE folders ADD CONSTRAINT valid_settings CHECK (JSON_VALID(`settings`))
        SQL);
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
