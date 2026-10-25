<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    /** The owner: an Arcadia `admin` membership, and approved, opens the editor. */
    const ALIAS_ADMIN = 1;

    /** Someone a guide is assigned to. */
    const ALIAS_READER = 5;

    protected $fillable = [
        'name',
        'description',
        'alias_number',
    ];

    protected $appends = [
        'alias',
    ];

    public function getAliasAttribute()
    {
        return self::aliasFor($this->alias_number);
    }

    /** The alias for an `alias_number`, without a row — `'unknown'` for anything unrecognised. */
    public static function aliasFor(?int $aliasNumber): string
    {
        return match ($aliasNumber) {
            self::ALIAS_ADMIN => 'admin',
            self::ALIAS_READER => 'reader',
            default => 'unknown',
        };
    }

    public $timestamps = false;

    public function scopeAdmin($query)
    {
        return $query->where('alias_number', self::ALIAS_ADMIN);
    }
}
