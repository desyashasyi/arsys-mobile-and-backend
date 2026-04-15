<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventApplicantDefense extends Model
{
    use HasFactory;
    protected $fillable = [];
    protected $guarded = [];
    protected $table = 'arsys_event_applicant_defense';



    public function event(){
        return $this->belongsTo(Event::class, 'event_id','id');
    }

    public function research(){
        return $this->belongsTo(Research::class,'research_id','id');
    }

    public function supervisor(){
        return $this->hasMany(ResearchSupervisor::class, 'research_id', 'research_id');
    }

    public function examiner(){
        return $this->hasMany(DefenseExaminer::class, 'applicant_id','id');
    }
    public function defenseExaminer(){
        return $this->hasMany(DefenseExaminer::class, 'applicant_id','id');
    }
    public function defenseExaminerAdditional(){
        return $this->hasMany(DefenseExaminer::class, 'applicant_id','id')->where('additional', 1);
    }

    public function defenseFirstExaminer(){
        return $this->hasOne(DefenseExaminer::class, 'applicant_id','id')->oldest();
    }

    public function examinerscore(){
        return $this->hasMany(DefenseExaminerScore::class,  ['applicant_id','id'], ['id','event_id']);
    }

   /*
    public function report(){
        return $this->hasMany(DefenseReport::class,  'applicant_id','id');
    }
    */

    public function space(){
        return $this->belongsTo(EventSpace::class,  'space_id','id'  );
    }
    public function session(){
        return $this->belongsTo(EventSession::class,  'session_id','id');
    }

    public function room(){
        return $this->hasMany(SeminarRoom::class, 'event_id', 'event_id');
    }

    public function special(){
        return $this->belongsTo(EventApplicantMarkType::class, 'id', 'mark_id');
    }

    public function applicantroom(){
        return $this->belongsTo(SeminarRoom::class, 'room_id', 'id');
    }

    public function previous (){
        return $this->hasOne(EventDefenseApplicant::class,'research_id', 'research_id');
                //->where('event_type', EventType::where('abbrev', 'PRE')->first()->id);
    }
}
