<?php

namespace Tests\Feature\Tax;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Tax\Models\TaxBusinessProfile;
use Tests\Feature\IAM\Concerns\InteractsWithRbac;
use Tests\TestCase;

class TaxAssetTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRbac;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->token = $this->tokenFor($this->makeUser(
            ['tax.view', 'tax.create', 'tax.export', 'tax.manage'],
            'owner',
        ));

        Storage::fake('s3');

        TaxBusinessProfile::create([
            'business_id' => $this->businessId,
            'business_type' => 'restaurant',
            'npwpd' => '03001295329004',
            'company_name' => 'Rm.Test',
        ]);
    }

    public function test_can_upload_signature_and_stamp(): void
    {
        $signature = UploadedFile::fake()->image('signature.png', 800, 400);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/tax/assets/signature', ['file' => $signature])
            ->assertOk();

        $this->assertSame('signature', $response->json('data.type'));
        $this->assertLessThanOrEqual(600, $response->json('data.width')); // resized down per config max_width
        Storage::disk('s3')->assertExists($response->json('data.path'));

        $this->withToken($this->token)->getJson('/api/v1/tax/assets')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_uploading_again_with_same_extension_overwrites_in_place(): void
    {
        $first = UploadedFile::fake()->image('signature.png', 500, 300);
        $firstResponse = $this->withToken($this->token)
            ->postJson('/api/v1/tax/assets/signature', ['file' => $first])
            ->assertOk();
        $firstPath = $firstResponse->json('data.path');

        $second = UploadedFile::fake()->image('signature.png', 500, 300);
        $secondResponse = $this->withToken($this->token)
            ->postJson('/api/v1/tax/assets/signature', ['file' => $second])
            ->assertOk();

        $this->assertSame($firstPath, $secondResponse->json('data.path'));
        Storage::disk('s3')->assertExists($firstPath);
        $this->withToken($this->token)->getJson('/api/v1/tax/assets')
            ->assertOk()
            ->assertJsonCount(1, 'data'); // still one row (upserted, not duplicated)
    }

    public function test_uploading_with_a_different_extension_deletes_the_old_file(): void
    {
        $first = UploadedFile::fake()->image('signature.png', 500, 300);
        $firstResponse = $this->withToken($this->token)
            ->postJson('/api/v1/tax/assets/signature', ['file' => $first])
            ->assertOk();
        $firstPath = $firstResponse->json('data.path');

        $second = UploadedFile::fake()->image('signature.jpg', 500, 300);
        $this->withToken($this->token)
            ->postJson('/api/v1/tax/assets/signature', ['file' => $second])
            ->assertOk();

        Storage::disk('s3')->assertMissing($firstPath);
        $this->withToken($this->token)->getJson('/api/v1/tax/assets')
            ->assertOk()
            ->assertJsonCount(1, 'data'); // still one row (upserted, not duplicated)
    }

    public function test_can_remove_asset(): void
    {
        $signature = UploadedFile::fake()->image('signature.png', 500, 300);
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/tax/assets/signature', ['file' => $signature])
            ->assertOk();
        $path = $response->json('data.path');

        $this->withToken($this->token)->deleteJson('/api/v1/tax/assets/signature')->assertOk();

        Storage::disk('s3')->assertMissing($path);
        $this->withToken($this->token)->getJson('/api/v1/tax/assets')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_rejects_unregistered_asset_type(): void
    {
        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $this->withToken($this->token)
            ->postJson('/api/v1/tax/assets/logo', ['file' => $file])
            ->assertStatus(422);
    }

    public function test_pdf_renders_with_uploaded_signature_and_stamp(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/tax/assets/signature', ['file' => UploadedFile::fake()->image('signature.png', 400, 200)])
            ->assertOk();
        $this->withToken($this->token)
            ->postJson('/api/v1/tax/assets/stamp', ['file' => UploadedFile::fake()->image('stamp.png', 400, 400)])
            ->assertOk();

        $generate = $this->withToken($this->token)->postJson('/api/v1/tax/generate', [
            'business_type' => 'restaurant', 'month' => 9, 'year' => 2027,
        ])->assertOk();

        $this->withToken($this->token)
            ->get("/api/v1/tax/reports/{$generate->json('data.id')}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
