<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['organization_id', 'center_id', 'user_id', 'candidate_number'])]
class Candidate extends Model
{
    //
}
