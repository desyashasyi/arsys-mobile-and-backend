<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefenseSupervisorPresence extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'arsys_defense_supervisor_presence';
    public function supervisor(){
        return $this->belongsTo(ResearchSupervisor::class, 'research_supervisor_id','id');
    }
}
