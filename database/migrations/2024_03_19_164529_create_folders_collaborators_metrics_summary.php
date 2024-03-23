<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('folders_collaborators_metrics_summary', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('metrics_type');
            $table->foreignId('collaborator_id');
            $table->foreignId('folder_id');
            $table->unsignedInteger('count');
            $table->unique(['collaborator_id', 'folder_id', 'metrics_type'], 'unique_metrics_summary');
            $table->timestamps();
        });
    }
};
