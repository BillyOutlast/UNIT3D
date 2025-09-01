<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PornMovieMeta extends Model
{
    protected $guarded = [];
    public $timestamps = false;

    public function torrent()
    {
        return $this->belongsTo(Torrent::class);
    }
}
