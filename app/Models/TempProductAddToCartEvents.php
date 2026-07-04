<?php

namespace App\Models;

use Database\Factories\TempProductAddToCartEventsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TempProductAddToCartEvents extends Model
{
    /** @use HasFactory<TempProductAddToCartEventsFactory> */
    use HasFactory;

    public $timestamps = false;
}
