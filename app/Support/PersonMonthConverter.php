<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class PersonMonthConverter
{
    public function convert(?string $hours, string $hoursPerPm): ?string
    {
        if ($hours === null) return null;
        $hourCents = $this->cents($hours, true);
        $factorCents = $this->cents($hoursPerPm, false);
        if ($factorCents <= 0) throw new InvalidArgumentException('Hours per Person-Month must be greater than zero.');
        $thousandths = intdiv($hourCents * 1000 + intdiv($factorCents, 2), $factorCents);
        return intdiv($thousandths, 1000) . '.' . str_pad((string) ($thousandths % 1000), 3, '0', STR_PAD_LEFT);
    }

    public function subtract(?string $actual, ?string $planned): ?string
    {
        if ($actual === null || $planned === null) return null;
        $difference = $this->cents($actual, true) - $this->cents($planned, true);
        $sign = $difference < 0 ? '-' : '';
        $difference = abs($difference);
        return $sign . intdiv($difference, 100) . '.' . str_pad((string) ($difference % 100), 2, '0', STR_PAD_LEFT);
    }

    public function pmVariance(?string $actual, ?string $planned, string $hoursPerPm): ?string
    {
        $hours = $this->subtract($actual, $planned);
        if ($hours === null) return null;
        $negative = str_starts_with($hours, '-');
        $value = $this->convert($negative ? substr($hours, 1) : $hours, $hoursPerPm);
        return $negative ? '-' . $value : $value;
    }

    private function cents(string $value, bool $allowZero): int
    {
        if (preg_match('/^(0|[1-9]\d{0,5})(?:\.(\d{1,2}))?$/', $value, $matches) !== 1) {
            throw new InvalidArgumentException('Invalid decimal value.');
        }
        $cents = (int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '', 2, '0');
        if (!$allowZero && $cents === 0) throw new InvalidArgumentException('Decimal divisor must be positive.');
        return $cents;
    }
}
