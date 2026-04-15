<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Student extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $fillable = [];
    protected $table = 'arsys_student';

    public function program() {
        return $this->belongsTo(Program::class, 'program_id', 'id');
    }

    public function getNimAttribute(): string
    {
        $code = $this->program?->code;
        return $code ? $code . '.' . $this->number : ($this->number ?? '-');
    }
    public function specialization() {
        return $this->belongsTo(Specialization::class, 'specialization_id', 'id' );
    }

    public function supervisor() {
        return $this->belongsTo(Staff::class, 'supervisor_id', 'id' );
    }

    public function user(){
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

}
