<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudyCompletion extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $fillable = [];
    protected $table = 'arsys_institution_study_completion';
    public function team(){
        return $this->hasMany(StudyCompletionTeam::class, 'study_completion_id','id' );
    }
    public function base(){
        return $this->belongsTo(StudyCompletionBase::class, 'study_completion_base_id','id' );
    }
}
