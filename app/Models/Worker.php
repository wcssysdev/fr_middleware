<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Worker extends Model
{
    use HasFactory;

    protected $table = 'fa_person';
    protected $primaryKey = "fa_person_id";
    public $timestamps = false;
   
    protected $fillable = [
        'personid',
        'firstname',
        'lastName',
        'orgcode',
        'orgname',
        'remark',
        'facepicture',
        'companyname',
        'email',
        'tel',
        'enableParkingSpace',
        'parkingSpaceNum',
        'sipId',
        'accessType',
        'faceIssueResult',
        'created_at'
    ];
}