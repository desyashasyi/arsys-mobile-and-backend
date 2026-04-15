<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefenseScoreRubric extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $fillable = [];
    protected $table = 'arsys_defense_rubric';

    public function base(){
        return $this->belongsTo(DefenseScoreRubricBase::class, 'rubric_id','id');
    }
}
