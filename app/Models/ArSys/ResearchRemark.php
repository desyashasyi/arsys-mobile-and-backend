<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ResearchRemark extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'arsys_research_remark';
    
    public function user(){
        return $this->belongsTo(User::class, 'discussant_id', 'id');
    }
}
