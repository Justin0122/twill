<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('library_folders', function (Blueprint $table) {
            $table->id();
            $table->enum('library', ['file', 'media']);
            $table->string('name', 255);
            $table->string('path', 1024);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
            $table->index(['library', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_folders');
    }
};
