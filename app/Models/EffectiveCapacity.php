<?php
declare(strict_types=1);
namespace App\Models;
final class EffectiveCapacity
{
    public function __construct(public readonly string$effectiveHours,public readonly string$source,public readonly string$standardHours,public readonly ?string$overrideHours){}
}
