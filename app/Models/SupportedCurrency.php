<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $code
 * @property string $name
 * @property string $symbol
 * @property int $minor_units
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'symbol', 'minor_units', 'is_active'])]
class SupportedCurrency extends Model
{
    protected $table = 'currencies';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minor_units' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
