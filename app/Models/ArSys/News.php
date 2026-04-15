<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    protected $table = 'arsys_news';
    protected $guarded = [];

    public function author()
    {
        return $this->belongsTo(Staff::class, 'author_id', 'id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id', 'id');
    }
}
