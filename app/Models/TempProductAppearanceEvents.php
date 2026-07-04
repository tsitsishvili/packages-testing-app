<?php

namespace App\Models;

use Database\Factories\TempProductAppearanceEventsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TempProductAppearanceEvents extends Model
{
    /** @use HasFactory<TempProductAppearanceEventsFactory> */
    use HasFactory;

    public $timestamps = false;
}
