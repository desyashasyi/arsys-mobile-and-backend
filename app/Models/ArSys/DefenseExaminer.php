<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefenseExaminer extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $fillable = [];
    protected $table = 'arsys_defense_examiner';
    public function staff(){
        return $this->belongsTo(Staff::class, 'examiner_id','id' );
    }
    public function event(){
        return $this->belongsTo(Event::class, 'event_id','id');
    }

    public function defenseApplicant(){
        return $this->belongsTo(EventApplicantDefense::class, 'applicant_id','id');
    }
    public function defenseExaminerPresence(){
        return $this->hasOne(DefenseExaminerPresence::class, 'defense_examiner_id','id');
    }
}
