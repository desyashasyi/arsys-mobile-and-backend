<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefenseApproval extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $fillable = [];
    protected $table = 'arsys_defense_approval';

    public function staff(){
        return $this->belongsTo(Staff::class, 'approver_id','id' );
    }

    public function research(){
        return $this->belongsTo(Research::class,'research_id','id');
    }

    public function defenseModel()
    {
        return $this->belongsTo(DefenseModel::class, 'defense_model_id', 'id');
    }

    public function defenseRole()
    {
        return $this->belongsTo(DefenseRole::class, 'approver_role', 'id');
    }
}
