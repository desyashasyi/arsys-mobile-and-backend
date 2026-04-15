<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResearchSupervisorDummy extends Model
{
    use HasFactory;
    protected $fillable = [];
    protected $guarded = [];
    protected $table = 'arsys_research_supervisor_dummy';

    public function staff(){
        return $this->belongsTo(Staff::class, 'supervisor_id','id' );
    }

    public function research(){
        return $this->belongsTo(Research::class, 'research_id', 'id');
    }
}
