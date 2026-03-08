<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Core\Domain\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserImportModel extends Model
{
    protected $table = 'user_imports';

    protected $fillable = [
        'tenant_id',
        'batch_id',
        'file_path',
        'status',
        'total_rows',
        'processed_rows',
        'created_count',
        'updated_count',
        'errors',
        'started_at',
        'completed_at',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantModel::class, 'tenant_id');
    }

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'errors' => 'array',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'created_count' => 'integer',
            'updated_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
