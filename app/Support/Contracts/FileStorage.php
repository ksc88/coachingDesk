<?php

namespace App\Support\Contracts;

use Illuminate\Http\UploadedFile;

interface FileStorage
{
    public function storeTenantFile(int $tenantId, string $folder, UploadedFile $file): string;

    public function temporaryUrl(string $path, int $minutes = 30): string;

    public function delete(string $path): bool;
}
