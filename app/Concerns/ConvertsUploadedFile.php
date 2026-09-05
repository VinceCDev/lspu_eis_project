<?php

namespace App\Concerns;

use Illuminate\Http\UploadedFile;

/**
 * Adapts Laravel's UploadedFile back into the $_FILES-shaped array the
 * ported App\Core\Uploader (kept framework-agnostic on purpose) expects.
 * Used by every controller that calls Uploader::store()/storeNamed().
 */
trait ConvertsUploadedFile
{
    protected function fileToArray(?UploadedFile $file): array
    {
        if (!$file) {
            return [];
        }

        return [
            'name' => $file->getClientOriginalName(),
            'type' => $file->getClientMimeType(),
            'tmp_name' => $file->getPathname(),
            'error' => $file->getError(),
            'size' => $file->getSize(),
        ];
    }
}
