<?php

declare(strict_types=1);

namespace Tests\Feature\Presentation\File;

use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class UploadFilesValidationTest extends TestCase
{
    public function test_upload_rejects_php_file(): void
    {
        $user = $this->authUser();

        $response = $this->actingAs($user)->post(route('api.files.upload'), [
            'user_id' => (string) $user->id,
            'files' => [
                UploadedFile::fake()->create('malicious.php', 100, 'application/x-php'),
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['files.0']);
    }

    public function test_upload_rejects_sh_file(): void
    {
        $user = $this->authUser();

        $response = $this->actingAs($user)->post(route('api.files.upload'), [
            'user_id' => (string) $user->id,
            'files' => [
                UploadedFile::fake()->create('script.sh', 100, 'application/x-sh'),
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['files.0']);
    }

    public function test_upload_accepts_pdf_file(): void
    {
        $user = $this->authUser();

        $response = $this->actingAs($user)->post(route('api.files.upload'), [
            'user_id' => (string) $user->id,
            'files' => [
                UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            ],
        ]);

        $response->assertJsonMissingValidationErrors(['files.0']);
    }

    public function test_upload_accepts_jpg_file(): void
    {
        $user = $this->authUser();

        $response = $this->actingAs($user)->post(route('api.files.upload'), [
            'user_id' => (string) $user->id,
            'files' => [
                UploadedFile::fake()->image('photo.jpg'),
            ],
        ]);

        $response->assertJsonMissingValidationErrors(['files.0']);
    }

    public function test_upload_accepts_png_file(): void
    {
        $user = $this->authUser();

        $response = $this->actingAs($user)->post(route('api.files.upload'), [
            'user_id' => (string) $user->id,
            'files' => [
                UploadedFile::fake()->image('photo.png'),
            ],
        ]);

        $response->assertJsonMissingValidationErrors(['files.0']);
    }

    public function test_upload_accepts_webp_file(): void
    {
        $user = $this->authUser();

        $response = $this->actingAs($user)->post(route('api.files.upload'), [
            'user_id' => (string) $user->id,
            'files' => [
                UploadedFile::fake()->create('photo.webp', 100, 'image/webp'),
            ],
        ]);

        $response->assertJsonMissingValidationErrors(['files.0']);
    }
}
