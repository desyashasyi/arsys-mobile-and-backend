<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinalDefenseExaminer extends Model
{
    use HasFactory;
    protected $fillable = [];
    protected $guarded = [];
    protected $table = 'arsys_seminar_examiner';
    public function staff(){
        return $this->belongsTo(Staff::class, 'examiner_id','id' );
    }
    public function finaldefenseExaminerPresence(){
        return $this->hasMany(FinalDefenseExaminerPresence::class, 'seminar_examiner_id','id');
    }

    public function event(){
        return $this->belongsTo(Event::class, 'event_id','id');
    }

    public function room(){
        return $this->belongsTo(FinalDefenseRoom::class, 'room_id','id');
    }

    public function presence(){
        return $this->hasOne(FinalDefenseExaminerPresence::class, 'seminar_examiner_id','id');
    }

}