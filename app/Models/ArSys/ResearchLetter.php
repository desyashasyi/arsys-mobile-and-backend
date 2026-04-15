<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResearchLetter extends Model
{
    use HasFactory;
    protected $fillable = [];
    protected $guarded = [];
    protected $table = 'arsys_research_letter';
    public function faculty(){
        return $this->belongsTo(FacultyLetter::class, 'faculty_letter_id','id' );
    }
    public function program(){
        return $this->belongsTo(ProgramLetter::class, 'program_letter_id','id' );
    }
    
}
