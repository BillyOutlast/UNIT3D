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
        $table->unsignedBigInteger('torrent_id');
        $table->json('raw')->nullable();
        $table->timestamps();

        $table->foreign('torrent_id')->references('id')->on('torrents')->onDelete('cascade');
    });
    }
    public function down(): void
    {
        Schema::dropIfExists('theporndb_scene_metas');
    }
};
