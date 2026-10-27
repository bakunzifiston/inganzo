<?php

namespace App\Services\Design;

use RuntimeException;

/**
 * The document was saved elsewhere (another tab or device) after this copy was loaded.
 */
class DesignConflictException extends RuntimeException
{
    public function __construct(public readonly int $currentRevision)
    {
        parent::__construct('The design was changed since it was loaded.');
    }
}
