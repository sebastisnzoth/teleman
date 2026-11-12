<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_bases', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->longText('content')->nullable();
            $table->timestamps();
        });

        Schema::table('ai_agents', function (Blueprint $table) {
            $table->foreignId('knowledge_base_id')->nullable()->after('knowledge_base')->constrained('knowledge_bases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->dropForeign(['knowledge_base_id']);
        });
        Schema::dropIfExists('knowledge_bases');
    }
};
