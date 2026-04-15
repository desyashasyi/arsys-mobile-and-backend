<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgramLetter extends Model
{
    use HasFactory;
    protected $fillable = [];
    protected $guarded = [];
    protected $table = 'arsys_institution_program_letter';
    public function base(){
        return $this->belongsTo(ProgramLetterBase::class, 'program_letter_base_id','id' );
    }
    
}
