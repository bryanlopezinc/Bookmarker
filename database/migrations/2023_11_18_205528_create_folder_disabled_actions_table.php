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
        Schema::create('folders_disabled_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id');
            $table->foreignId('feature_id');
            $table->unique(['folder_id', 'feature_id']);
        });
    }
};
