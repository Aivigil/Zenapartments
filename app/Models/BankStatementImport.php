<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatementImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_account', 'period_start', 'period_end',
        'source_filename', 'source_hash',
        'total_lines', 'matched_lines', 'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'total_lines' => 'integer',
            'matched_lines' => 'integer',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }
}
