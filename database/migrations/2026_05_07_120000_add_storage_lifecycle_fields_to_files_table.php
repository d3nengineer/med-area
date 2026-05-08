<?php

use Domain\File\Enums\FileLifecycleState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->string('lifecycle_state')->default(FileLifecycleState::AVAILABLE->value);
            $table->string('storage_operation_id')->nullable()->index();
            $table->string('storage_error_code')->nullable();
            $table->text('storage_error_message')->nullable();
            $table->timestamp('lifecycle_changed_at')->nullable();
            $table->timestamp('storage_reconciled_at')->nullable();
            $table->timestamp('upload_completed_at')->nullable();
            $table->timestamp('delete_requested_at')->nullable();
        });

        DB::table('files')
            ->update([
                'lifecycle_state' => FileLifecycleState::AVAILABLE->value,
                'lifecycle_changed_at' => now(),
                'storage_reconciled_at' => now(),
                'upload_completed_at' => DB::raw('created_at'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn([
                'lifecycle_state',
                'storage_operation_id',
                'storage_error_code',
                'storage_error_message',
                'lifecycle_changed_at',
                'storage_reconciled_at',
                'upload_completed_at',
                'delete_requested_at',
            ]);
        });
    }
};
