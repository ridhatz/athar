<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Decides whether an uploaded file may be stored. Never trusts what the browser says:
 *  - the extension must be on the allow-list,
 *  - the file's real type (sniffed from its bytes) must be acceptable for that extension,
 *  - the size must be within the limit.
 * So "shell.php" is refused, and so is a script renamed to "photo.png".
 */
final class FileGuard
{
    /**
     * @return array{extension: string, mime: string, name: string}
     *
     * @throws ValidationException
     */
    public function inspect(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            $this->fail(__('errors.file_upload_failed'));
        }

        $maxKb = (int) config('athar.attachments.max_kb');

        if ($file->getSize() > $maxKb * 1024) {
            $this->fail(__('errors.file_too_large', ['max' => round($maxKb / 1024)]));
        }

        $name = $this->cleanName($file->getClientOriginalName());
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = (array) config('athar.attachments.types');

        if ($extension === '' || ! array_key_exists($extension, $allowed)) {
            $this->fail(__('errors.file_type_not_allowed'));
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());

        if (! in_array($mime, $allowed[$extension], true)) {
            $this->fail(__('errors.file_content_mismatch'));
        }

        return ['extension' => $extension, 'mime' => $mime, 'name' => $name];
    }

    /** The name shown to people: no path parts, no control characters, bounded length. */
    public function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '';
        $name = trim($name);

        return mb_substr($name === '' ? 'file' : $name, 0, 255);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
