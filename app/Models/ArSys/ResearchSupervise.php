<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResearchSupervise extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $fillable = [];
    protected $table = 'arsys_research_supervise';
    
    public function discussion(){
        return $this->hasMany(ResearchSuperviseDiscussion::class, 'supervise_id','id' );
    }
    public function research(){
        return $this->belongsTo(Research::class, 'research_id', 'id');
    }

}
