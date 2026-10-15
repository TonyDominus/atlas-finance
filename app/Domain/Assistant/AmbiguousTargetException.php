<?php

declare(strict_types=1);

namespace App\Domain\Assistant;

use RuntimeException;

final class AmbiguousTargetException extends RuntimeException
{
    /**
     * @param  list<string>  $candidates
     */
    public function __construct(public readonly array $candidates)
    {
        parent::__construct('Servono più dettagli: '.implode(', ', $candidates).'.');
    }
}
