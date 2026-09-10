<?php

namespace App\Traits;

/**
 * BACKWARD-COMPAT SHIM. The SPAR activity-logging trait now lives in the
 * spar-core package (Zapmed\SparCore\Concerns\LogsSparActivity). This shim
 * re-exports it under the original App\Traits name so any remaining host
 * references keep working. New code should use the package trait directly.
 */
trait LogsSparActivity
{
    use \Zapmed\SparCore\Concerns\LogsSparActivity;
}
