<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinalDefenseSupervisorPresence extends Model
{
    use HasFactory;
    protected $fillable = [];
    protected $guarded = [];
    protected $table = 'arsys_seminar_supervisor_presence';

    /*
    public function applicant(){
        return $this->belongsTo(EventApplicantFinalDefense::class, 'applicant_id','id');
    }
    */

    public function applicant(){
        return $this->belongsTo(EventApplicantFinalDefense::class, 'applicant_id','id');
    }
    public function event(){
        return $this->belongsTo(Event::class, 'event_id','id');
    }
}
