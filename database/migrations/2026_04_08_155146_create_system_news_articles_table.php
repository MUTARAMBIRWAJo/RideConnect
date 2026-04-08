<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_news_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('excerpt');
            $table->text('body')->nullable();
            $table->string('category'); // 'Feature Release', 'Maintenance', 'Update', 'Safety', 'Report', etc.
            $table->string('icon')->default('heroicon-o-newspaper');
            $table->string('color')->default('primary'); // 'success', 'warning', 'danger', 'info', 'primary'
            $table->boolean('is_published')->default(true);
            $table->dateTime('published_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_news_articles');
    }
};
