<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = (string) config('visual-builder.tables.templates', 'builder_templates');

        Schema::create($tableName, static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->string('type', 20)->default('section');
            $table->string('thumbnail_path', 500)->nullable();
            $table->json('payload');
            $table->timestamps();

            $table->index('created_at', $table->getTable().'_created_idx');
            $table->index('type', $table->getTable().'_type_idx');
        });
    }

    public function down(): void
    {
        $tableName = (string) config('visual-builder.tables.templates', 'builder_templates');
        Schema::dropIfExists($tableName);
    }
};
