<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('imports_history', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->string('document_line_number');
            $table->uuid('import_id')->index();
            $table->json('tags')->nullable();
            $table->tinyInteger('status')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imports_history');
    }
};
