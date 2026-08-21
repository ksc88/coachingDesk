<?php

namespace App\Support\Services;

use App\Support\Contracts\FileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LocalTenantFileStorage implements FileStorage
{
    public function storeTenantFile(int $tenantId, string $folder, UploadedFile $file): string
    {
        $ext = $file->getClientOriginalExtension();
        $name = Str::uuid().($ext ? '.'.$ext : '');
        $path = "tenants/{$tenantId}/{$folder}/{$name}";

        Storage::disk('local')->putFileAs("tenants/{$tenantId}/{$folder}", $file, $name);

        return $path;
    }

    public function temporaryUrl(string $path, int $minutes = 30): string
    {
        // Local disk fallback: signed route-like path for pilot.
        return url('/app/files/'.base64_encode($path).'?expires='.(time() + ($minutes * 60)));
    }

    public function delete(string $path): bool
    {
        return Storage::disk('local')->delete($path);
    }
}
