<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReportExportRequest extends Model
{
    public const FORMAT_PDF = 'PDF';

    public const FORMAT_CSV = 'CSV';

    public const STATE_PENDING = 'pending';

    public const STATE_PROCESSING = 'processing';

    public const STATE_AVAILABLE = 'available';

    public const STATE_FAILED = 'failed';

    protected $fillable = [
        'report_version_id',
        'requested_by',
        'format',
        'state',
        'request_token',
        'attempts',
        'queued_at',
        'started_at',
        'finished_at',
        'lease_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'queued_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'lease_expires_at' => 'immutable_datetime',
        ];
    }

    public function reportVersion(): BelongsTo
    {
        return $this->belongsTo(ReportVersion::class, 'report_version_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
