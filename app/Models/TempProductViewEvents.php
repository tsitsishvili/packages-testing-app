<?php

namespace App\Models;

use Database\Factories\TempProductViewEventsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TempProductViewEvents extends Model
{
    /** @use HasFactory<TempProductViewEventsFactory> */
    use HasFactory;

    public $timestamps = false;
}
