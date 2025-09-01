<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('theporndb_scene_metas', function (Blueprint $table) {
            $table->id();
            $table->string('theporndb_scene_id')->index();
            $table->string('title')->nullable();
            $table->string('type')->nullable();
            $table->string('url')->nullable();
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->json('performers')->nullable();
            $table->json('site')->nullable();
            $table->json('tags')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('theporndb_scene_metas');
    }
};
