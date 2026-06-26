<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'exam_id', 'candidate_id', 'status'])]
class Result extends Model
{
    //
}
