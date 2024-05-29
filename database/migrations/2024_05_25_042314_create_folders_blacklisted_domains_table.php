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
        Schema::create('folders_blacklisted_domains', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 18)->unique();
            $table->unsignedBigInteger('folder_id');
            $table->string('given_url');
            $table->string('resolved_domain');
            $table->string('domain_hash', 20);
            $table->unsignedBigInteger('created_by');
            $table->unique(['folder_id', 'domain_hash']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folders_blacklisted_domains');
    }
};
