<?php

use App\Enums\NotificationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Stringable;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        $jsonSchema = file_get_contents(base_path('database/JsonSchema/notifications_1.0.0.json'));

        DB::unprepared(
            <<<SQL
                ALTER TABLE notifications ADD CONSTRAINT validate_notification_data CHECK(JSON_SCHEMA_VALID('$jsonSchema', data));
                ALTER TABLE notifications ADD CONSTRAINT ensure_id_is_uuid CHECK(IS_UUID(id))
            SQL
        );

        $this->addTypeColumnConstraint();
    }

    private function addTypeColumnConstraint(): void
    {
        $allowed = new Stringable();

        foreach (NotificationType::values() as $value) {
            $allowed = $allowed->append("'$value',");
        }

        $allowed = $allowed->replaceLast(',', '');

        DB::unprepared(
            <<<SQL
                ALTER TABLE notifications ADD CONSTRAINT type_is_valid CHECK(type IN ($allowed))
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
        Schema::dropIfExists('notifications');
    }
};
