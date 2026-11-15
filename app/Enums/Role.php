<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Operator = 'operator';
    case Customer = 'customer';
    case Courier = 'courier';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Operator => 'Logistics Operator',
            self::Customer => 'Customer',
            self::Courier => 'Courier',
        };
    }
}
