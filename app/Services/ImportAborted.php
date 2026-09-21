<?php

namespace App\Services;

/**
 * Thrown from an import checkpoint when this worker no longer owns the import (it was cancelled, or another worker took over
 * after this one stopped heart-beating). Thrown inside the chunk's transaction, so the chunk is rolled back and the run stops.
 */
class ImportAborted extends \RuntimeException
{
}
