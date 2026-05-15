<?php

namespace Tests\Unit;

use App\Enums\AttachmentType;
use App\Enums\TransactionType;
use PHPUnit\Framework\TestCase;

final class AttachmentTypeTest extends TestCase
{
    public function test_invoice_endpoint(): void
    {
        $this->assertSame('invoices/123/attachment', AttachmentType::Invoice->endpoint('123'));
    }

    public function test_bill_endpoint(): void
    {
        $this->assertSame('bills/abc/attachment', AttachmentType::Bill->endpoint('abc'));
    }

    public function test_transaction_type_maps_bill_to_bill_attachment(): void
    {
        $this->assertSame(AttachmentType::Bill, TransactionType::Bill->attachmentType());
        $this->assertSame(AttachmentType::Bill, TransactionType::VendorBill->attachmentType());
    }

    public function test_transaction_type_maps_invoice_to_invoice_attachment(): void
    {
        $this->assertSame(AttachmentType::Invoice, TransactionType::Invoice->attachmentType());
        $this->assertSame(AttachmentType::Invoice, TransactionType::CustomerInvoice->attachmentType());
    }

    public function test_payment_types_have_no_attachment(): void
    {
        $this->assertNull(TransactionType::VendorPayment->attachmentType());
        $this->assertNull(TransactionType::CustomerPayment->attachmentType());
    }

    public function test_from_zoho_handles_case_and_unknown(): void
    {
        $this->assertSame(TransactionType::Bill, TransactionType::fromZoho('Bill'));
        $this->assertSame(TransactionType::Invoice, TransactionType::fromZoho('INVOICE'));
        $this->assertSame(TransactionType::Other, TransactionType::fromZoho('something_unknown'));
        $this->assertSame(TransactionType::Other, TransactionType::fromZoho(null));
        $this->assertSame(TransactionType::Other, TransactionType::fromZoho(''));
    }
}
