<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained('languages')->cascadeOnDelete();
            $table->string('group')->default('app');
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['language_id', 'group', 'key']);
            $table->index(['language_id', 'group']);
            $table->index('key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
