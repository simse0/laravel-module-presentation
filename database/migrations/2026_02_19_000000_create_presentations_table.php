<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presentations', function (Blueprint $table) {
            $table->id();
            $table->string('presentable_type');
            $table->unsignedBigInteger('presentable_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->json('slide_order')->nullable();
            $table->json('text_overrides')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['presentable_type', 'presentable_id']);
            $table->index(['presentable_type', 'presentable_id', 'user_id'], 'presentations_subject_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presentations');
    }
};
