<?php

namespace App\Enums;

enum AttachmentType: string
{
    case Invoice = 'invoice';
    case Bill = 'bill';

    public function endpoint(string $id): string
    {
        return match ($this) {
            self::Invoice => "invoices/{$id}/attachment",
            self::Bill => "bills/{$id}/attachment",
        };
    }
}
