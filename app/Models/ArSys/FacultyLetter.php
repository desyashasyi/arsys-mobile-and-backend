<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacultyLetter extends Model
{
    use HasFactory;
    protected $fillable = [];
    protected $guarded = [];
    protected $table = 'arsys_institution_faculty_letter';
    public function base(){
        return $this->belongsTo(FacultyLetterBase::class, 'faculty_letter_base_id','id' );
    }

}
