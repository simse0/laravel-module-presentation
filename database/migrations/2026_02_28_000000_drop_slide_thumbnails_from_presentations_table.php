<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('presentations', 'slide_thumbnails')) {
            Schema::table('presentations', function (Blueprint $table) {
                $table->dropColumn('slide_thumbnails');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('presentations', 'slide_thumbnails')) {
            Schema::table('presentations', function (Blueprint $table) {
                $table->json('slide_thumbnails')->nullable()->after('settings');
            });
        }
    }
};
