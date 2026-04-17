<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('visual-builder.tables.sections', 'builder_sections');

        Schema::table($tableName, static function (Blueprint $table): void {
            $table->unsignedBigInteger('parent_id')->nullable()->after('instance_key');
            $table->unsignedInteger('column_index')->nullable()->after('parent_id');

            $table->index(
                ['parent_id', 'column_index', 'sort_order'],
                $table->getTable().'_children_lookup_idx',
            );
        });
    }

    public function down(): void
    {
        $tableName = (string) config('visual-builder.tables.sections', 'builder_sections');

        Schema::table($tableName, static function (Blueprint $table): void {
            $table->dropIndex($table->getTable().'_children_lookup_idx');
            $table->dropColumn(['parent_id', 'column_index']);
        });
    }
};
