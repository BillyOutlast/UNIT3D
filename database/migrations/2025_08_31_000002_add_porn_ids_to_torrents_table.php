<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('torrents', function (Blueprint $table) {
            $table->string('theporndb_scene_id')->nullable()->after('igdb');
            $table->string('theporndb_movie_id')->nullable()->after('theporndb_scene_id');
            $table->string('theporndb_jav_id')->nullable()->after('theporndb_movie_id');
            $table->string('stashdb_id')->nullable()->after('theporndb_jav_id');
            $table->string('fansdb_id')->nullable()->after('stashdb_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('torrents', function (Blueprint $table) {
            $table->dropColumn([
                'theporndb_scene_id',
                'theporndb_movie_id',
                'theporndb_jav_id',
                'stashdb_id',
                'fansdb_id',
            ]);
        });
    }
};
