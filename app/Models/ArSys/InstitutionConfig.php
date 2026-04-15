<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstitutionConfig extends Model
{
    use HasFactory;
    protected $fillable = [];
    protected $guarded = [];
    protected $table = 'arsys_institution_config';
    public function data(){
        return $this->belongsTo(InstitutionConfigBase::class, 'config_base_id','id' );
    }

    public function specialization(){
        return $this->hasMany(Specialization::class, 'program_id','program_id' );

    }
}
