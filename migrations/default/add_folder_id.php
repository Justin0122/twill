<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('library_folders', function (Blueprint $table) {
            $table->unique(['library', 'path']);
        });

        Schema::table('twill_medias', function (Blueprint $table) {
            $table->unsignedBigInteger('folder_id')->nullable()->after('height');
            $table->foreign('folder_id')->references('id')->on('library_folders')->nullOnDelete();
        });

        Schema::table('twill_files', function (Blueprint $table) {
            $table->unsignedBigInteger('folder_id')->nullable()->after('size');
            $table->foreign('folder_id')->references('id')->on('library_folders')->nullOnDelete();
        });

        DB::transaction(function () {
            $medias = DB::table('twill_medias')
                ->select('id', 'folder_path')
                ->whereNotNull('folder_path')->where('folder_path', '!=', '')
                ->get();

            foreach ($medias as $m) {
                $folder = DB::table('library_folders')
                    ->where('library', 'media')
                    ->where('path', $m->folder_path)
                    ->first();

                if ($folder) {
                    DB::table('twill_medias')->where('id', $m->id)->update(['folder_id' => $folder->id]);
                }
            }

            if (Schema::hasTable('twill_files') && Schema::hasColumn('twill_files', 'folder_path')) {
                $files = DB::table('twill_files')
                    ->select('id', 'folder_path')
                    ->whereNotNull('folder_path')->where('folder_path', '!=', '')
                    ->get();

                foreach ($files as $f) {
                    $folder = DB::table('library_folders')
                        ->where('library', 'file')
                        ->where('path', $f->folder_path)
                        ->first();

                    if ($folder) {
                        DB::table('twill_files')->where('id', $f->id)->update(['folder_id' => $folder->id]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('twill_medias', function (Blueprint $table) {
            $table->dropForeign(['folder_id']);
            $table->dropColumn('folder_id');
        });

        Schema::table('twill_files', function (Blueprint $table) {
            $table->dropForeign(['folder_id']);
            $table->dropColumn('folder_id');
        });

        Schema::table('library_folders', function (Blueprint $table) {
            $table->dropUnique(['library', 'path']);
        });
    }
};
