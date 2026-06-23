<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalLog extends Model
{
    protected $fillable = ['journal_entry_id', 'user_id', 'action', 'notes'];
}
