<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->longText('slides_data')->nullable()->after('title');
            $table->longText('report_data')->nullable()->after('slides_data');
            $table->string('version_name')->nullable()->after('report_data');
        });

        DB::table('presentations')->whereNull('name')->eachById(function ($row) {
            $baseName = strtolower(class_basename($row->presentable_type)) . '-' . $row->presentable_id;
            DB::table('presentations')->where('id', $row->id)->update(['name' => $baseName]);
        });

        Schema::table('presentations', function (Blueprint $table) {
            $table->string('name')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('presentations', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropColumn(['name', 'slides_data', 'report_data', 'version_name']);
        });
    }
};
