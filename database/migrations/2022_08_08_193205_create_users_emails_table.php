<?php

declare(strict_types=1);

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
        Schema::create('users_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained('users')->cascadeOnDelete();
            $table->string('email')->unique();
            $table->timestamp('verified_at');
        });

        DB::unprepared(<<<SQL
            CREATE TRIGGER ensure_email_unique
            BEFORE INSERT
            ON users_emails
            FOR EACH ROW
            BEGIN
                IF EXISTS (SELECT email
                                    FROM users
                                    WHERE users.email = NEW.email)
                THEN SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Email must be unique';
                 END IF;
            END
        SQL);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users_emails');
    }
};
