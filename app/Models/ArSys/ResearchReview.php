<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResearchReview extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $fillable = [];
    protected $table = 'arsys_research_review';

    public function staff(){
        return $this->belongsTo(Staff::class, 'reviewer_id','id' );
    }
    public function decision(){
        return $this->belongsTo(ResearchReviewDecisionType::class, 'decision_id','id' );
    }

    public function file(){
        return $this->hasOne(ResearchReviewFile::class, 'review_id','id' );
    }
    public function research(){
        return $this->belongsTo(Research::class, 'research_id', 'id');
    }
    public function review(){
        return $this->belongsTo(ResearchLog::class, 'research_id', 'id')
            ->where('type_id', ResearchLogType::where('code', 'REV')->first()->id)
            ->where('status', null);
    }
}
