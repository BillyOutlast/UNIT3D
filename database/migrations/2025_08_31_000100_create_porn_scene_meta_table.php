<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('porn_scene_meta', function (Blueprint $table) {
            $table->id();
            $table->string('scene_id')->unique();
            $table->foreignId('torrent_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable();
            $table->date('release_date')->nullable();
            $table->string('studio')->nullable();
            $table->json('performers')->nullable();
            $table->json('urls')->nullable();
            $table->text('details')->nullable();
            $table->string('director')->nullable();
            $table->json('raw')->nullable();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('porn_scene_meta');
    }
};
