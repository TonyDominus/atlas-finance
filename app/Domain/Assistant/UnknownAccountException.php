<?php

declare(strict_types=1);

namespace App\Domain\Assistant;

use RuntimeException;

final class UnknownAccountException extends RuntimeException
{
    public function __construct(string $name)
    {
        parent::__construct("Nessun conto attivo corrisponde a «{$name}».");
    }
}
