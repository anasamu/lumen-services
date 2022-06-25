<?php

namespace App\Models;

use App\Traits\UsesUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Example extends Model
{
    // if you have sensitive data in your column make sure add the column name in $hidden array
    use UsesUuid, HasFactory, SoftDeletes;
    protected $table = 'example';
    protected $hidden = ['deleted_at','password'];
    protected $guarded = ['uuid'];
}
