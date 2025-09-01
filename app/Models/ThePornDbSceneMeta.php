<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThePornDbSceneMeta extends Model
{
    protected $table = 'theporndb_scene_metas';
    protected $guarded = [];
    public $timestamps = true;

    public function torrent()
    {
        return $this->belongsTo(Torrent::class);
    }
}
