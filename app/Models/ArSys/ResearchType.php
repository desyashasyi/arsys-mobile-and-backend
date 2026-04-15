<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResearchType extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $fillable = [];
    protected $table = 'arsys_research_type';
    public function data(){
        return $this->belongsTo(ResearchTypeBase::class, 'research_type_base_id','id' );
    }

    public function base(){
        return $this->belongsTo(ResearchTypeBase::class, 'research_type_base_id','id' );
    }

    public function researchTypeCount(){
        return $this->hasMany(Research::class, 'type_id','id' );
    }

    public function examination(){
        return $this->hasMany(ResearchTypeExamination::class, 'research_type_id','id');
    }
}
