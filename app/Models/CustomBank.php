<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CustomBank extends Model
{
    protected $fillable = ['name', 'key', 'icon'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($bank) {
            if (empty($bank->key)) {
                $bank->key = Str::slug($bank->name, '_');
            }
        });
    }
}
