<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefenseExaminerPresence extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'arsys_defense_examiner_presence';
    public function examiner(){
        return $this->belongsTo(DefenseExaminer::class, 'defense_examiner_id','id');
    }
}
