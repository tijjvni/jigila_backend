<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\RefundStatus;
use App\Mail\CredentialsMail;
use App\Mail\ForgotPasswordMail;
use App\Mail\InvoiceCreatedMail;
use App\Mail\TicketCreatedMail;
use App\Mail\TicketReplyMail;
use App\Mail\WelcomeMail;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    private function createInApp(User $user, string $type, string $title, string $body, array $data = []): void
    {
        $user->notifications()->create([
            'type'  => $type,
            'title' => $title,
            'body'  => $body,
            'data'  => $data ?: null,
        ]);
    }

    public function sendWelcome(User $user): void
    {
        try {
            Mail::to($user->email)->queue(new WelcomeMail($user));
        } catch (\Throwable $e) {
            Log::error('Failed to send welcome email', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        $this->createInApp(
            $user,
            'welcome',
            'Welcome to Jigila!',
            'Your account has been created successfully. Start tracking your vehicle imports today.',
        );
    }

    public function sendForgotPassword(User $user, string $otp): void
    {
        try {
            Mail::to($user->email)->queue(new ForgotPasswordMail($user, $otp));
        } catch (\Throwable $e) {
            Log::error('Failed to send forgot password email', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    public function sendCredentials(User $user, string $temporaryPassword): void
    {
        try {
            Mail::to($user->email)->queue(new CredentialsMail($user, $temporaryPassword));
        } catch (\Throwable $e) {
            Log::error('Failed to send credentials email', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    public function sendInvoiceCreated(Invoice $invoice): void
    {
        $invoice->loadMissing(['user', 'order']);
        $user = $invoice->user;

        try {
            Mail::to($user->email)->queue(new InvoiceCreatedMail($invoice));
        } catch (\Throwable $e) {
            Log::error('Failed to send invoice email', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
        }

        $this->createInApp(
            $user,
            'invoice_created',
            'New Invoice Generated',
            "Invoice {$invoice->invoice_number} for \${$invoice->amount} has been generated. Please log in to pay.",
            ['invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number, 'amount' => $invoice->amount],
        );
    }

    public function sendTicketCreated(Ticket $ticket): void
    {
        try {
            $ticket->loadMissing('user');
            Mail::to($ticket->user->email)->queue(new TicketCreatedMail($ticket));
        } catch (\Throwable $e) {
            Log::error('Failed to send ticket created email', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Shipping paperwork landed on an order — tell the customer it is ready to
     * download (BUG-035).
     */
    public function notifyDocumentUploaded(Order $order, OrderDocument $document): void
    {
        $label = DocumentType::labels()[$document->type->value] ?? 'Document';

        $this->createInApp(
            $order->user,
            'document_uploaded',
            'New Document Available',
            "{$label} has been added to your order for VIN {$order->vin}. You can download it from the order page.",
            ['order_id' => $order->id, 'document_id' => $document->id, 'document_type' => $document->type->value],
        );
    }

    /**
     * Cancellation notice to both sides of the order (BUG-064).
     */
    public function notifyOrderCancelled(Order $order, User $actor): void
    {
        $order->loadMissing('user');
        $byAdmin = $actor->role === 'admin';

        $this->createInApp(
            $order->user,
            'order_cancelled',
            'Order Cancelled',
            $byAdmin
                ? "Your order for VIN {$order->vin} was cancelled by Jigila. Reason: {$order->cancellation_reason}"
                : "Your order for VIN {$order->vin} has been cancelled. Reason: {$order->cancellation_reason}",
            ['order_id' => $order->id],
        );

        if (!$byAdmin) {
            $this->notifyAdmins(
                'order_cancelled',
                'Order Cancelled by Customer',
                "{$order->user->name} cancelled order for VIN {$order->vin}. Reason: {$order->cancellation_reason}",
                ['order_id' => $order->id],
            );
        }
    }

    /**
     * Vessel / container / ETA details changed (BUG-034).
     */
    public function notifyShippingUpdated(Order $order): void
    {
        $order->loadMissing('user');

        $detail = $order->vessel_name ? "Vessel {$order->vessel_name}" : 'Shipping details';

        $this->createInApp(
            $order->user,
            'shipping_updated',
            'Shipping Details Updated',
            "{$detail} has been updated for your order (VIN {$order->vin}). Open the order to see the latest tracking information.",
            ['order_id' => $order->id],
        );
    }

    /**
     * Refund moved along the requested → approved → processed path (BUG-055).
     */
    public function notifyRefundStatus(Invoice $invoice, RefundStatus $status): void
    {
        $invoice->loadMissing('user');

        $body = match ($status) {
            RefundStatus::Requested => "We have received your refund request for invoice {$invoice->invoice_number}. Our team will review it shortly.",
            RefundStatus::Approved  => "Your refund of \${$invoice->refund_amount} on invoice {$invoice->invoice_number} has been approved and is being processed.",
            RefundStatus::Processed => "Your refund of \${$invoice->refund_amount} on invoice {$invoice->invoice_number} has been processed. Allow 3–5 business days for it to reach your account.",
            RefundStatus::Rejected  => "Your refund request on invoice {$invoice->invoice_number} was not approved. Contact support if you would like to discuss this.",
        };

        $this->createInApp(
            $invoice->user,
            'refund_' . $status->value,
            RefundStatus::labels()[$status->value],
            $body,
            ['invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number],
        );
    }

    /**
     * Hour-based reminder for an invoice that is still unpaid (BUG-033).
     */
    public function sendPaymentReminder(Invoice $invoice): void
    {
        $invoice->loadMissing('user');

        $this->createInApp(
            $invoice->user,
            'payment_reminder',
            'Payment Reminder',
            "Invoice {$invoice->invoice_number} for \${$invoice->amount} is still outstanding. Please complete payment to keep your shipment on schedule.",
            ['invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number, 'amount' => $invoice->amount],
        );

        try {
            Mail::to($invoice->user->email)->queue(new InvoiceCreatedMail($invoice));
        } catch (\Throwable $e) {
            Log::error('Failed to send payment reminder email', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
        }
    }

    public function notifyAdmins(string $type, string $title, string $body, array $data = []): void
    {
        User::where('role', 'admin')->chunkById(100, function ($admins) use ($type, $title, $body, $data) {
            foreach ($admins as $admin) {
                $this->createInApp($admin, $type, $title, $body, $data);
            }
        });
    }

    public function sendTicketReply(Ticket $ticket, TicketMessage $message, User $recipient): void
    {
        try {
            $message->loadMissing('user');
            Mail::to($recipient->email)->queue(new TicketReplyMail($ticket, $message));
        } catch (\Throwable $e) {
            Log::error('Failed to send ticket reply email', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);
        }
    }
}
