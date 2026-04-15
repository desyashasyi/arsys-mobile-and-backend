<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResearchLog extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'arsys_research_log';

    public function type()
    {
        return $this->belongsTo(ResearchLogType::class, 'type_id', 'id');
    }
}
