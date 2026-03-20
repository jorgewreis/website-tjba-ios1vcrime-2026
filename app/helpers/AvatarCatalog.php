<?php
declare(strict_types=1);

/**
 * Catálogo de avatares por preset (imagens versionadas em public/, sem upload).
 *
 * @phpstan-type AvatarEntry array{slug: string, url: string, label: string}
 */
final class AvatarCatalog
{
    private const MANIFEST_REL = '/assets/img/avatars/presets/manifest.json';

    private const DEFAULT_SLUG = 'default';

    /** @var list<AvatarEntry>|null */
    private static ?array $cached = null;

    /**
     * @return list<AvatarEntry>
     */
    public static function listForUi(): array
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $path = self::manifestPath();
        if (!is_file($path) || !is_readable($path)) {
            self::$cached = [self::fallbackEntry()];
            return self::$cached;
        }

        $json = file_get_contents($path);
        if ($json === false) {
            self::$cached = [self::fallbackEntry()];
            return self::$cached;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            self::$cached = [self::fallbackEntry()];
            return self::$cached;
        }

        $out = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $slug = trim((string) ($row['slug'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            if ($slug === '' || $url === '' || $label === '') {
                continue;
            }
            if (!self::isSafePublicUrl($url)) {
                continue;
            }
            $out[] = ['slug' => $slug, 'url' => $url, 'label' => $label];
        }

        if ($out === []) {
            self::$cached = [self::fallbackEntry()];
            return self::$cached;
        }

        self::$cached = $out;
        return self::$cached;
    }

    public static function isAllowedSlug(string $slug): bool
    {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }
        foreach (self::listForUi() as $entry) {
            if ($entry['slug'] === $slug) {
                return true;
            }
        }
        return false;
    }

    public static function urlForSlug(string $slug): string
    {
        $slug = trim($slug);
        foreach (self::listForUi() as $entry) {
            if ($entry['slug'] === $slug) {
                return $entry['url'];
            }
        }
        foreach (self::listForUi() as $entry) {
            if ($entry['slug'] === self::DEFAULT_SLUG) {
                return $entry['url'];
            }
        }
        return '/assets/img/avatars/presets/default.svg';
    }

    public static function getDefaultSlug(): string
    {
        return self::DEFAULT_SLUG;
    }

    private static function manifestPath(): string
    {
        return dirname(__DIR__, 2) . '/public' . self::MANIFEST_REL;
    }

    /**
     * Apenas URLs relativas ao site, path público (sem esquema, sem ..).
     */
    private static function isSafePublicUrl(string $url): bool
    {
        if ($url === '' || str_contains($url, '..')) {
            return false;
        }
        if (!str_starts_with($url, '/')) {
            return false;
        }
        if (str_contains($url, "\0") || str_contains($url, '//')) {
            return false;
        }
        return true;
    }

    /**
     * @return AvatarEntry
     */
    private static function fallbackEntry(): array
    {
        return [
            'slug' => self::DEFAULT_SLUG,
            'url' => '/assets/img/avatars/presets/default.svg',
            'label' => 'Padrão',
        ];
    }
}
