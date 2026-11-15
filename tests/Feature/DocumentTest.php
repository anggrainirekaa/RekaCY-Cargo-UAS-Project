<?php

namespace Tests\Feature;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function upload(User $by, Shipment $s, array $o = [])
    {
        return $this->actingAs($by)->post("/shipments/{$s->id}/documents", $o + [
            'type' => 'POD', 'title' => 'Signed POD', 'recipient_name' => 'Budi', 'received_at' => now()->subHour()->toDateTimeString(),
            'file' => UploadedFile::fake()->create('pod.pdf', 100, 'application/pdf'),
        ]);
    }

    public function test_staff_uploads_pod_and_owner_can_download_it(): void
    {
        $c = User::factory()->create();
        $s = Shipment::factory()->create(['customer_id' => $c->id]);

        $this->upload(User::factory()->operator()->create(), $s)->assertRedirect();
        $doc = $s->documents()->first();
        $this->assertNotNull($doc);
        Storage::disk('local')->assertExists($doc->path);
        $this->assertStringNotContainsString('public', $doc->path);

        $this->actingAs($c)->get("/documents/{$doc->id}/download")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($c)->get('/documents')->assertOk()->assertSee('Signed POD');
        $this->assertArrayNotHasKey('path', $doc->toArray());
    }

    public function test_other_customers_and_guests_cannot_download(): void
    {
        $s = Shipment::factory()->create();
        $this->upload(User::factory()->operator()->create(), $s);
        $doc = $s->documents()->first();

        $this->actingAs(User::factory()->create())->get("/documents/{$doc->id}/download")->assertForbidden();
        auth()->logout();
        $this->get("/documents/{$doc->id}/download")->assertRedirect('/login');
    }

    public function test_customers_cannot_upload_or_delete(): void
    {
        $c = User::factory()->create();
        $s = Shipment::factory()->create(['customer_id' => $c->id]);

        $this->upload($c, $s)->assertForbidden();
        $this->assertSame(0, $s->documents()->count());

        $this->upload(User::factory()->operator()->create(), $s);
        $this->actingAs($c)->delete('/documents/'.$s->documents()->first()->id)->assertForbidden();
    }

    public function test_validation_rejects_bad_types_sizes_and_incomplete_pod(): void
    {
        $s = Shipment::factory()->create();
        $op = User::factory()->operator()->create();

        $this->upload($op, $s, ['file' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php')])->assertSessionHasErrors('file');
        $this->upload($op, $s, ['file' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')])->assertSessionHasErrors('file');
        $this->upload($op, $s, ['recipient_name' => ''])->assertSessionHasErrors('recipient_name');
        $this->upload($op, $s, ['type' => 'INVOICE', 'recipient_name' => null, 'received_at' => null])->assertSessionHasNoErrors();
        $this->assertSame(1, $s->documents()->count());
    }

    public function test_staff_can_delete_document_and_file_is_removed(): void
    {
        $s = Shipment::factory()->create();
        $op = User::factory()->operator()->create();
        $this->upload($op, $s);
        $doc = $s->documents()->first();

        $this->actingAs($op)->delete("/documents/{$doc->id}")->assertRedirect();
        Storage::disk('local')->assertMissing($doc->path);
        $this->assertDatabaseMissing('shipment_documents', ['id' => $doc->id]);
    }
}
