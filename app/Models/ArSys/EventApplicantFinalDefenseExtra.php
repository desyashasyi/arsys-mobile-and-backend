<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventApplicantFinalDefenseExtra extends Model
{
    use HasFactory;
    protected $fillable = [];
    protected $guarded = [];
    protected $table = 'arsys_event_applicant_seminar_extra';

    public function research(){
        return $this->belongsTo(Research::class,'research_id','id');
    }
}
