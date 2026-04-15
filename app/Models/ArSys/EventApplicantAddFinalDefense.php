<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventApplicantAddFinalDefense extends Model
{
    use HasFactory;
    protected $fillable = [];
    protected $guarded = [];
    protected $table = 'arsys_event_applicant_final_defense_extra';

    public function research(){
        return $this->belongsTo(Research::class,'research_id','id');
    }
}
