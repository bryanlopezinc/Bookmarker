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
        Schema::create('folders_collaborators_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->index();
            $table->foreignId('user_id')->index();
            $table->foreignId('permission_id');
            $table->unique(['folder_id', 'user_id', 'permission_id'], 'unique_permission');
            $table->timestamp('created_at');
        });

        DB::unprepared(<<<SQL
            CREATE TRIGGER ensure_collaborator_is_not_folder_owner
            BEFORE INSERT
            ON folders_collaborators_permissions
            FOR EACH ROW
            BEGIN
                IF EXISTS (SELECT id
                                    FROM folders
                                    WHERE folders.id = NEW.folder_id
                                    AND folders.user_id = NEW.user_id)
                THEN SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Cannot save permissions for folder owner. Folder owner has all permissions';
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
        Schema::dropIfExists('folders_collaborators_permissions');
    }
};
