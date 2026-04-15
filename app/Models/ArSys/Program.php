<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    
    protected $fillable = [];
    protected $guarded = [];
    protected $table = 'arsys_institution_program';
    use HasFactory;

    public function cluster(){
        return $this->hasOne(Cluster::class, 'program_id','id' );
    }
    public function level(){
        return $this->belongsTo(Level::class, 'level_id','id' );
    }
    public function faculty(){
        return $this->belongsTo(Faculty::class, 'faculty_id','id' );
    }

    public function specialization(){
        return $this->hasMany(Specialization::class, 'id', 'program_id');
    }

    public function role(){
        return $this->hasOne(SystemRole::class, 'id','program_id' );
    }
    public function staff(){
        return $this->belongsTo(Staff::class, 'staff_id','id' );
    }

    public function researchConfig_EnableSuperviseDuration(){
        return $this->hasOne(ResearchConfig::class, 'program_id','id')
            ->where('config_base_id', ResearchConfigBase::where('code', 'MINIMUM_DAY_OF_SUPERVISE')->first()->id);
    }
}
