<?php

namespace App\Models\ArSys;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
class ResearchSuperviseDiscussion extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $fillable = [];
    protected $table = 'arsys_research_supervise_discussion';
    public function user(){
        return $this->belongsTo(User::class, 'discussant_id', 'id');
    }
}
