<?php
declare(strict_types=1);

interface SalaAudienciasAtaStorage
{
    public function rootPath(): string;

    public function isUploadedFile(string $path): bool;

    public function moveUploadedFile(string $source, string $destination): bool;

    public function isFile(string $path): bool;

    public function deleteFile(string $path): bool;
}

final class NativeSalaAudienciasAtaStorage implements SalaAudienciasAtaStorage
{
    public function __construct(private string $rootPath)
    {
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }

    public function isUploadedFile(string $path): bool
    {
        return is_uploaded_file($path);
    }

    public function moveUploadedFile(string $source, string $destination): bool
    {
        return move_uploaded_file($source, $destination);
    }

    public function isFile(string $path): bool
    {
        return is_file($path);
    }

    public function deleteFile(string $path): bool
    {
        if (!file_exists($path) && !is_link($path)) {
            return true;
        }
        return @unlink($path);
    }
}
