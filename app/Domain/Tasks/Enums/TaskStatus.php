<?php

namespace App\Domain\Tasks\Enums;

enum TaskStatus: string
{
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';

    /**
     * Resolve a TaskStatus enum case from a validated input string.
     *
     * @param  string  $status  Requested status value applied by this method.
     * @return self TaskStatus enum case matching the provided input value.
     */
    public static function fromInput(string $status): self
    {
        return self::from($status);
    }
}
