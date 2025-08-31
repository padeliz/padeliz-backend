<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ValidationsSite extends Model
{
    use HasFactory;

    protected $table = 'validations_site';

    protected $primaryKey = 'error';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'error',
        'vars',
        'message_en',
    ];
}
