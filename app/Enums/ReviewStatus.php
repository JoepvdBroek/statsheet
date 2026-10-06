<?php

namespace App\Enums;

/**
 * Where a Weekly Review's generation stands. A pending or failed review keeps the content of its last successful generation, if any.
 */
enum ReviewStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Failed = 'failed';
}
