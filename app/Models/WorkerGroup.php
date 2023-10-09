<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkerGroup extends Model
{
    use HasFactory;

    protected $table = 'fa_group';
    protected $primaryKey = "fa_group_id";
    public $timestamps = false;
    
    protected $fillable = [
        "orgCode",
        "parentOrgCode",
        "orgName",
        "children",
        "childNum",
        "authority",
        "remark",
        "created_at"
    ];
}