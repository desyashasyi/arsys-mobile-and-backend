<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudyCompletionTeam extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $fillable = [];
    protected $table = 'arsys_institution_study_completion_team';
    public function staff(){
        return $this->belongsTo(Staff::class, 'staff_id','id' );
    }
}
