<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResearchTypeExamination extends Model
{
    use HasFactory;
    protected $fillable = [];
    protected $guarded = [];
    protected $table = 'arsys_research_type_examination';
    public function event(){
        return $this->belongsTo(EventType::class, 'event_type_id','id');
    }
}
