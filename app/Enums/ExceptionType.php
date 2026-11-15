<?php

namespace App\Enums;

enum ExceptionType: string
{
    case CustomsHold = 'CUSTOMS_HOLD';
    case WeatherDelay = 'WEATHER_DELAY';
    case PortCongestion = 'PORT_CONGESTION';
    case Damaged = 'DAMAGED';
    case Misrouted = 'MISROUTED';
    case DocumentationIssue = 'DOCUMENTATION_ISSUE';
    case DeliveryFailed = 'DELIVERY_FAILED';

    public function label(): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $this->value)));
    }

    public function defaultSeverity(): Severity
    {
        return match ($this) {
            self::Damaged, self::Misrouted, self::DeliveryFailed => Severity::High,
            self::CustomsHold, self::DocumentationIssue => Severity::Medium,
            self::WeatherDelay, self::PortCongestion => Severity::Low,
        };
    }
}
