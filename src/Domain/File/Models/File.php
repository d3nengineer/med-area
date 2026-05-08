<?php

declare(strict_types=1);

namespace Domain\File\Models;

use Domain\File\Enums\FileLifecycleState;
use Domain\User\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Shared\Enums\Storage;

/**
 * @property string $id
 * @property string|null $user_id
 * @property Storage $storage
 * @property string $endpoint
 * @property string $bucket
 * @property string $key
 * @property integer $size
 * @property FileLifecycleState $lifecycle_state
 * @property string|null $storage_operation_id
 * @property string|null $storage_error_code
 * @property string|null $storage_error_message
 * @property \Illuminate\Support\Carbon|null $lifecycle_changed_at
 * @property \Illuminate\Support\Carbon|null $storage_reconciled_at
 * @property \Illuminate\Support\Carbon|null $upload_completed_at
 * @property \Illuminate\Support\Carbon|null $delete_requested_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereStorage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereEndpoint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereBucket($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|File whereDeletedAt($value)
 * @mixin \Eloquent
 */
class File extends Model
{
    /** @use HasFactory<\Domain\File\Factories\FileFactory> */
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'storage',
        'endpoint',
        'bucket',
        'key',
        'size',
        'lifecycle_state',
        'storage_operation_id',
        'storage_error_code',
        'storage_error_message',
        'lifecycle_changed_at',
        'storage_reconciled_at',
        'upload_completed_at',
        'delete_requested_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'storage' => Storage::class,
            'size' => 'int',
            'lifecycle_state' => FileLifecycleState::class,
            'lifecycle_changed_at' => 'datetime',
            'storage_reconciled_at' => 'datetime',
            'upload_completed_at' => 'datetime',
            'delete_requested_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
