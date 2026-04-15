<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResearchMilestoneLog extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $fillable = [];
    protected $table = 'arsys_research_milestone_log';
    public function detail(){
        return $this->belongsTo(ResearchMilestone::class, 'milestone_id', 'id')->where('research_model_id', ResearchModel::where('code', 'DEF')->first()->id)->latest();
    }
}
