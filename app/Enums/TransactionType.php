<?php

namespace App\Enums;

enum TransactionType: string
{
    case Invoice = 'invoice';
    case CustomerInvoice = 'customer_invoice';
    case Bill = 'bill';
    case VendorBill = 'vendor_bill';
    case VendorPayment = 'vendor_payment';
    case CustomerPayment = 'customer_payment';
    case Other = 'other';

    public static function fromZoho(?string $raw): self
    {
        if ($raw === null || $raw === '') {
            return self::Other;
        }

        return self::tryFrom(strtolower($raw)) ?? self::Other;
    }

    public function attachmentType(): ?AttachmentType
    {
        return match ($this) {
            self::Bill, self::VendorBill => AttachmentType::Bill,
            self::Invoice, self::CustomerInvoice => AttachmentType::Invoice,
            default => null,
        };
    }
}
