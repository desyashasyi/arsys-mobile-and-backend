<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResearchSupervisorScore extends Model
{
    use HasFactory;
    protected $fillable = ['research_supervisor_id', 'score', 'remark'];
    protected $table = 'arsys_research_supervisor_score';

    public function researchSupervisor()
    {
        return $this->belongsTo(ResearchSupervisor::class, 'research_supervisor_id', 'id');
    }
}
