<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores public images under generated names on the configured public disk.
 * User-supplied filenames and paths are never trusted.
 */
class ImageStorage
{
    /**
     * Validation rules shared by every public image upload.
     *
     * @return list<string>
     */
    public static function rules(): array
    {
        return [
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:'.config('hms.uploads.max_image_kb'),
            'dimensions:min_width=200,min_height=200,max_width=8000,max_height=8000',
        ];
    }

    public function store(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, ['disk' => $this->disk()]);

        if ($path === false) {
            throw new \RuntimeException('The image could not be stored.');
        }

        return $path;
    }

    /**
     * Store a replacement and remove the previous file only after the new one is saved.
     */
    public function replace(UploadedFile $file, string $directory, ?string $previousPath): string
    {
        $path = $this->store($file, $directory);

        $this->delete($previousPath);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk($this->disk())->delete($path);
        }
    }

    private function disk(): string
    {
        return (string) config('hms.uploads.public_disk');
    }
}
