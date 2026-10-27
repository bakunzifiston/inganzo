<?php

namespace App\Enums;

/**
 * Status-like enums that can be rendered with <x-badge>.
 */
interface HasBadge
{
    public function label(): string;

    /**
     * One of: neutral, info, success, warning, danger, accent.
     */
    public function tone(): string;
}
