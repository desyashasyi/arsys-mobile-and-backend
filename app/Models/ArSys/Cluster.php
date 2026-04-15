<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cluster extends Model
{
    use HasFactory;
    protected $fillable = [];
    protected $guarded = [];
    protected $table = 'arsys_institution_cluster';
    public function data(){
        return $this->belongsTo(ClusterBase::class, 'cluster_base_id','id' );
    }
    public function program(){
        return $this->hasMany(Program::class, 'program_id','id' );
    }
}
