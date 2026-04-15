<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefenseExaminerScoreRubric extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $fillable = [];
    protected $table = 'arsys_defense_examiner_rubric';
    public function rubric(){
        return $this->belongsTo(DefenseScoreRubric::class, 'defense_rubric_id','id');
    }
}
