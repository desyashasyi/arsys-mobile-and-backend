<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;
    protected $fillable = [];
    protected $guarded = [];
    protected $table = 'arsys_event';


    public function examiners(){
        return $this->hasMany(DefenseExaminer::class, 'event_id','id');
    }

    public function defenseApplicant(){
        return $this->hasMany(EventApplicantDefense::class, 'event_id','id');
    }
    public function defenseApplicantPublish(){
        return $this->hasMany(EventApplicantDefense::class, 'event_id','id')
            ->where('publish', 1);
    }


    public function seminarApplicant(){
        return $this->hasMany(EventApplicantSeminar::class, 'event_id','id');
    }
    public function seminarApplicantPublish(){
        return $this->hasMany(EventApplicantSeminar::class, 'event_id','id')
            ->where('publish', 1);
    }

    public function finaldefenseApplicant(){
        return $this->hasMany(EventApplicantFinalDefense::class, 'event_id','id');
    }
    public function finaldefenseApplicantPublish(){
        return $this->hasMany(EventApplicantFinalDefense::class, 'event_id','id')
            ->where('publish', 1);
    }

    public function finaldefenseRoom(){
        return $this->hasMany(FinalDefenseRoom::class, 'event_id','id');
    }

    public function unsetFinaldefenseRoom(){
        return $this->hasMany(FinalDefenseRoom::class, 'event_id','id')->where('space_id', null)
            ->orWhere('session_id', null)->orWhereDoesntHave('examiner')->orWhereDoesntHave('applicant');
    }

    public function seminarRoom(){
        return $this->hasMany(SeminarRoom::class, 'event_id','id');
    }

    public function unsetSeminarRoom(){
        return $this->hasMany(SeminarRoom::class, 'event_id','id')->where('space_id', null)
            ->orWhere('session_id', null)->orWhereDoesntHave('examiner')->orWhereDoesntHave('applicant');
    }
    public function program(){
        return $this->belongsTo(Program::class, 'program_id','id');
    }

    public function letter(){
        return $this->hasMany(EventLetter::class, 'event_id', 'id');
    }

    public function finaldefenseassignmentletter(){
        return $this->hasMany(EventAssignmentLetter::class, 'event_id', 'id');
    }
    public function type(){
        return $this->belongsTo(EventType::class, 'event_type_id', 'id');
    }

    // Menambahkan relasi session yang hilang
    public function session(){
        return $this->belongsTo(EventSession::class, 'event_session_id', 'id');
    }
}
