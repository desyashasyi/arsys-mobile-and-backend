<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResearchConfig extends Model
{
    use HasFactory;
    protected $fillable = [];
    protected $guarded = [];
    protected $table = 'arsys_research_config';
    public function data(){
        return $this->belongsTo(ResearchConfigBase::class, 'config_base_id','id' );
    }
}
