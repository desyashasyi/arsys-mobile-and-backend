<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class Staff extends Model
{
    use HasFactory, SoftDeletes;
    protected $guarded = [];
    protected $fillable = [];
    protected $table = 'arsys_staff';

    public function getNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function program(){
        return $this->belongsTo(Program::class, 'program_id','id' );
    }

    public function position(){
        return $this->belongsTo(StaffPosition::class, 'position_id','id' );
    }
    public function structure(){
        return $this->belongsTo(StaffStructure::class, 'structure_id','id' );
    }
    public function status(){
        return $this->belongsTo(StaffStatus::class, 'status_id','id' );
    }
    public function cluster(){
        return $this->belongsTo(Cluster::class, 'program_id','id' );
    }
    public function specialization(){
        return $this->belongsTo(Specialization::class, 'specialization_id','id' );
    }
    public function role(){
        return $this->hasMany(StaffRole::class, 'staff_id','id' );
    }
    public function type(){
        return $this->belongsTo(StaffType::class, 'staff_type_id','id' );
    }
    public function user(){
        return $this->belongsTo(User::class, 'user_id','id' );
    }
    public function firstSPV(){
        return $this->hasMany(ResearchSupervisor::class, 'supervisor_id', 'id')->where('order',1);
    }

    public function firstSPVActive(){
        return $this->hasMany(ResearchSupervisor::class, 'supervisor_id', 'id')->where('order',1)
                ->whereHas('research', function($query){
                    $query->whereHas('active', function($query){
                        $query->where('status', 1);
                    });
                });
    }

    public function secondSPV(){
        return $this->hasMany(ResearchSupervisor::class, 'supervisor_id', 'id')->where('order',2);
    }
    public function secondSPVActive(){
        return $this->hasMany(ResearchSupervisor::class, 'supervisor_id', 'id')->where('order',2)
                ->whereHas('research', function($query){
                    $query->whereHas('active', function($query){
                        $query->where('status', 1);
                    });
                });
    }

    public function reviewer(){
        return $this->hasMany(ResearchReview::class, 'reviewer_id', 'id')->where('approval_date',null)
                ->whereHas('research', function($query){
                    $query->whereHas('review', function($query){
                        $query->where('status', 1);
                    });
                });
    }

    public function firstDefenseExaminer(){
        return $this->hasMany(DefenseExaminer::class, 'examiner_id', 'id')->where('order',1);
    }
    public function secondDefenseExaminer(){
        return $this->hasMany(DefenseExaminer::class, 'examiner_id', 'id')->where('order',null);
    }
    public function defenseExaminer(){
        return $this->hasMany(DefenseExaminer::class, 'supervisor_id', 'id')->where('event_id',12);
    }


}
