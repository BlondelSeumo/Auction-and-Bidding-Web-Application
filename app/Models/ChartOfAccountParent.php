<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChartOfAccountParent extends Model
{
    protected $fillable = [
        'name',
        'sub_type',
        'type',
        'parent',
        'created_by',
    ];

}
