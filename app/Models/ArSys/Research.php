<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Research extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'arsys_research';

    public static function boot() {
        parent::boot();
        static::deleting(function($research) {
            $research->history()->delete();
            $research->proposalFile()->delete();
            $research->remark()->delete();
            $research->supervise()->delete();
            $research->reviewers()->delete(); // Ganti nama dari proposalReview
            $research->supervisor()->delete();
            $research->supervisorexternal()->delete();
        });
    }

    public function active()
    {
        return $this->hasOne(ResearchLog::class, 'research_id', 'id')
            ->where('status', 1)
            ->whereIn('type_id', function ($query) {
                $query->select('id')->from('arsys_research_log_type')->where('code', 'ACT');
            });
    }

    public function milestone(){
        return $this->belongsTo(ResearchMilestone::class, 'milestone_id', 'id');
    }

    public function supervise(){
        return $this->hasMany(ResearchSupervise::class, 'research_id', 'id');
    }

    public function proposalFile(){
        return $this->hasMany(ResearchFile::class, 'research_id', 'id')->where('file_type', 1);
    }

    // Mengganti nama proposalReview menjadi reviewers untuk kejelasan
    public function reviewers(){
        return $this->hasMany(ResearchReview::class, 'research_id', 'id');
    }

    public function supervisor(){
        return $this->hasMany(ResearchSupervisor::class, 'research_id', 'id');
    }

    public function supervisorexternal(){
        return $this->hasOne(ResearchSupervisorExternal::class, 'research_id', 'id');
    }

    public function type(){
        return $this->belongsTo(ResearchType::class,  'type_id', 'id');
    }

    public function student(){
        return $this->belongsTo(Student::class, 'student_id', 'id');
    }

    public function defenseApproval(){
        return $this->hasMany(DefenseApproval::class, 'research_id', 'id');
    }

    public function predefenseApproval()
    {
        return $this->hasMany(DefenseApproval::class, 'research_id')->whereHas('defenseModel', function ($query) {
            $query->where('description', 'Pre defense');
        });
    }

    public function finaldefenseApproval()
    {
        return $this->hasMany(DefenseApproval::class, 'research_id')->whereHas('defenseModel', function ($query) {
            $query->where('description', 'Final defense');
        });
    }

    public function seminarApproval()
    {
        return $this->hasMany(DefenseApproval::class, 'research_id')->whereHas('defenseModel', function ($query) {
            $query->where('description', 'Seminar');
        });
    }

    public function predefenseApproved()
    {
        return $this->predefenseApproval()->whereNotNull('decision');
    }

    public function finaldefenseApproved()
    {
        return $this->finaldefenseApproval()->whereNotNull('decision');
    }

    public function approvalRequest(){
        return $this->hasMany(DefenseApproval::class, 'research_id', 'id')->where('decision', null);
    }

    public function history(){
        return $this->hasMany(ResearchLog::class, 'research_id', 'id');
    }

    public function remark(){
        return $this->hasMany(ResearchRemark::class, 'research_id', 'id');
    }

    public function DEFDONE()
    {
        return $this->hasOne(ResearchLog::class, 'research_id', 'id')->whereHas('type', function ($query) {
            $query->where('code', 'DEFDONE');
        });
    }
}
