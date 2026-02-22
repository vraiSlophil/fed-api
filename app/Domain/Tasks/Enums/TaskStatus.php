<?php

namespace App\Domain\Tasks\Enums;

enum TaskStatus: string
{
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';

    public static function fromInput(string $status): self
    {
        return self::from($status);
    }
}
