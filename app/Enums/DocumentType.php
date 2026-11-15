<?php

namespace App\Enums;

enum DocumentType: string
{
    case Pod = 'POD';
    case Invoice = 'INVOICE';
    case BillOfLading = 'BILL_OF_LADING';
    case Customs = 'CUSTOMS';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Pod => 'Proof of delivery',
            self::BillOfLading => 'Bill of lading',
            default => ucfirst(strtolower($this->value)),
        };
    }
}
