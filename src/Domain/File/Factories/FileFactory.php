<?php

declare(strict_types=1);

namespace Domain\File\Factories;

use Domain\File\Enums\FileLifecycleState;
use Domain\File\Models\File;
use Illuminate\Database\Eloquent\Factories\Factory;
use Shared\Enums\Storage;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Domain\File\Models\File>
 */
class FileFactory extends Factory
{
    protected $model = File::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => fake()->uuid(),
            'storage' => Storage::S3_TESTING,
            'endpoint' => config('filesystems.disks.s3-testing.endpoint', 'https://storage.yandexcloud.net'),
            'bucket' => config('filesystems.disks.s3-testing.bucket', 'test-bucket'),
            'key' => microtime() . random_int(1, 99999),
            'size' => fake()->randomDigit(),
            'lifecycle_state' => FileLifecycleState::AVAILABLE,
            'lifecycle_changed_at' => now(),
            'storage_reconciled_at' => now(),
            'upload_completed_at' => now(),
        ];
    }
}
