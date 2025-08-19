<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('twill_files', function (Blueprint $table) {
            $table->string('folder_path', 1024)->default('');
        });
        Schema::table('twill_medias', function (Blueprint $table) {
            $table->string('folder_path', 1024)->default('');
        });
    }

    public function down(): void
    {
        Schema::table('twill_files', function (Blueprint $table) {
            $table->dropColumn('folder_path');
        });
        Schema::table('twill_medias', function (Blueprint $table) {
            $table->dropColumn('folder_path');
        });
    }
};
