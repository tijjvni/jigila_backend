<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderDocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function customerWithOrder(): array
    {
        $user  = User::factory()->create(['role' => 'user']);
        $order = Order::factory()->create(['user_id' => $user->id]);

        return [$user, $order];
    }

    private function upload(Order $order, User $admin, string $type = 'bill_of_lading'): OrderDocument
    {
        $this->actingAs($admin)
            ->postJson("/api/v1/admin/orders/{$order->id}/documents", [
                'type' => $type,
                'file' => UploadedFile::fake()->create('bol.pdf', 120, 'application/pdf'),
            ])->assertStatus(201);

        return OrderDocument::where('order_id', $order->id)->latest('id')->firstOrFail();
    }

    // ─── Upload ───────────────────────────────────────────────────────────────

    public function test_admin_can_upload_a_document(): void
    {
        $admin     = $this->admin();
        [, $order] = $this->customerWithOrder();

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/orders/{$order->id}/documents", [
                'type' => DocumentType::BillOfLading->value,
                'note' => 'Original BOL from the carrier.',
                'file' => UploadedFile::fake()->create('bol.pdf', 200, 'application/pdf'),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', DocumentType::BillOfLading->value)
            ->assertJsonPath('data.original_name', 'bol.pdf');

        $document = OrderDocument::firstOrFail();
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_customer_cannot_upload_a_document(): void
    {
        [$user, $order] = $this->customerWithOrder();

        $this->actingAs($user)
            ->postJson("/api/v1/admin/orders/{$order->id}/documents", [
                'type' => DocumentType::BillOfLading->value,
                'file' => UploadedFile::fake()->create('bol.pdf', 10, 'application/pdf'),
            ])->assertStatus(403);
    }

    public function test_invalid_document_type_is_rejected(): void
    {
        $admin     = $this->admin();
        [, $order] = $this->customerWithOrder();

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/orders/{$order->id}/documents", [
                'type' => 'passport',
                'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
            ])->assertStatus(422)->assertJsonValidationErrors(['type']);
    }

    public function test_oversized_file_is_rejected(): void
    {
        $admin     = $this->admin();
        [, $order] = $this->customerWithOrder();

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/orders/{$order->id}/documents", [
                'type' => DocumentType::BillOfLading->value,
                'file' => UploadedFile::fake()->create('huge.pdf', 11000, 'application/pdf'),
            ])->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    public function test_disallowed_extension_is_rejected(): void
    {
        $admin     = $this->admin();
        [, $order] = $this->customerWithOrder();

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/orders/{$order->id}/documents", [
                'type' => DocumentType::BillOfLading->value,
                'file' => UploadedFile::fake()->create('payload.exe', 10),
            ])->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    public function test_upload_notifies_the_customer(): void
    {
        $admin          = $this->admin();
        [$user, $order] = $this->customerWithOrder();

        $this->upload($order, $admin);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type'    => 'document_uploaded',
        ]);
    }

    // ─── Listing & download ───────────────────────────────────────────────────

    public function test_owner_can_list_documents(): void
    {
        $admin          = $this->admin();
        [$user, $order] = $this->customerWithOrder();
        $this->upload($order, $admin);

        $this->actingAs($user)
            ->getJson("/api/v1/orders/{$order->id}/documents")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_other_customer_cannot_list_documents(): void
    {
        $admin     = $this->admin();
        $intruder  = User::factory()->create(['role' => 'user']);
        [, $order] = $this->customerWithOrder();
        $this->upload($order, $admin);

        $this->actingAs($intruder)
            ->getJson("/api/v1/orders/{$order->id}/documents")
            ->assertStatus(403);
    }

    public function test_owner_can_download_a_document(): void
    {
        $admin          = $this->admin();
        [$user, $order] = $this->customerWithOrder();
        $document       = $this->upload($order, $admin);

        $this->actingAs($user)
            ->get("/api/v1/orders/{$order->id}/documents/{$document->id}/download")
            ->assertStatus(200)
            ->assertDownload('bol.pdf');
    }

    public function test_other_customer_cannot_download_a_document(): void
    {
        $admin     = $this->admin();
        $intruder  = User::factory()->create(['role' => 'user']);
        [, $order] = $this->customerWithOrder();
        $document  = $this->upload($order, $admin);

        $this->actingAs($intruder)
            ->get("/api/v1/orders/{$order->id}/documents/{$document->id}/download")
            ->assertStatus(403);
    }

    /**
     * Both ids come from the URL, so a document id from another order must not
     * be readable just because the caller owns the order in the path.
     */
    public function test_document_from_another_order_returns_404(): void
    {
        $admin            = $this->admin();
        [$user, $myOrder] = $this->customerWithOrder();
        [, $otherOrder]   = $this->customerWithOrder();

        $foreignDocument = $this->upload($otherOrder, $admin);

        $this->actingAs($user)
            ->get("/api/v1/orders/{$myOrder->id}/documents/{$foreignDocument->id}/download")
            ->assertStatus(404);
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_a_document_and_the_blob_goes_with_it(): void
    {
        $admin     = $this->admin();
        [, $order] = $this->customerWithOrder();
        $document  = $this->upload($order, $admin);
        $path      = $document->path;

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/orders/{$order->id}/documents/{$document->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('order_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_customer_cannot_delete_a_document(): void
    {
        $admin          = $this->admin();
        [$user, $order] = $this->customerWithOrder();
        $document       = $this->upload($order, $admin);

        $this->actingAs($user)
            ->deleteJson("/api/v1/admin/orders/{$order->id}/documents/{$document->id}")
            ->assertStatus(403);
    }
}
