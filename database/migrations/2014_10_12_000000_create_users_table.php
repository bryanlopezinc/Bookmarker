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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 15)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('full_name')->storedAs("CONCAT(first_name, ' ', last_name)")->index();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('two_fa_mode', ['None', 'Email']);
            $table->rememberToken();
            $table->timestamps();
        });

        DB::unprepared(<<<SQL
            CREATE TRIGGER ensure_email_is_not_another_users_secondary_email
            BEFORE INSERT
            ON users
            FOR EACH ROW
            BEGIN
                IF EXISTS (SELECT email
                                    FROM users_emails
                                    WHERE users_emails.email = NEW.email)
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
        Schema::dropIfExists('users');
    }
};
