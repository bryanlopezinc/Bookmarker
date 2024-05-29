<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('folders_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->index();
            $table->unsignedTinyInteger('type');
            $table->json('data');
            $table->timestamp('created_at');
            $table->timestamp('updated_at')->nullable();
        });

        DB::unprepared(
            <<<SQL
                ALTER TABLE notifications ADD CONSTRAINT valid_folder_activity_data CHECK (JSON_VALID(`data`));
            SQL
        );
    }
};
