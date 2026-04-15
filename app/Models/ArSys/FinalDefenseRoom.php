<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinalDefenseRoom extends Model
{
    use HasFactory;
    protected $fillable = [];
    protected $guarded = [];
    protected $table = 'arsys_seminar_room';

    public function examiner(){
        return $this->hasMany(FinalDefenseExaminer::class, 'room_id','id');
    }

    public function moderator(){
        return $this->belongsTo(Staff::class, 'moderator_id','id');
    }
    public function applicant(){
        return $this->hasMany(EventApplicantFinalDefense::class, 'room_id','id')->orderBy('id', 'ASC');
    }

    public function event(){
        return $this->belongsTo(Event::class, 'event_id','id');
    }
    public function space(){
        return $this->belongsTo(EventSpace::class,  'space_id','id'  );
    }
    public function session(){
        return $this->belongsTo(EventSession::class,  'session_id','id'  );
    }

}
