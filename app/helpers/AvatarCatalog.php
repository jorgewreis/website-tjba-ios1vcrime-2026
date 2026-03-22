<?php
declare(strict_types=1);

require_once __DIR__ . '/UserInitialsHelper.php';

/**
 * Catálogo de avatares: imagens em public/assets/img/avatars/vivid/ (sem upload).
 * Modo especial "initials": sem imagem — exibe iniciais com paleta de sessão (login).
 *
 * @phpstan-type AvatarEntry array{slug: string, url: string, label: string, is_initial?: bool}
 */
final class AvatarCatalog
{
    /** Slug reservado: avatar por iniciais (não usa arquivo). */
    public const SLUG_INITIALS = 'initials';

    /** Legado / migração: tratar como initials. */
    private const SLUG_LEGACY_DEFAULT = 'default';

    private const DIR_VIVID_REL = '/assets/img/avatars/vivid';

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * Nomes exibidos no select (slugs = nome do ficheiro sem extensão).
     * H-* = avatares masculinos, M-* = femininos.
     *
     * @var array<string, string>
     */
    private const VIVID_DISPLAY_NAMES = [
        'H-001' => 'Rafael',
        'H-002' => 'Lucas',
        'H-003' => 'Gabriel',
        'H-004' => 'Matheus',
        'H-005' => 'Bruno',
        'H-006' => 'Thiago',
        'H-007' => 'André',
        'H-008' => 'Felipe',
        'M-001' => 'Ana',
        'M-002' => 'Beatriz',
        'M-003' => 'Camila',
        'M-004' => 'Daniela',
        'M-005' => 'Eduarda',
        'M-006' => 'Fernanda',
        'M-007' => 'Gabriela',
        'M-008' => 'Helena',
        'M-009' => 'Isabela',
        'M-010' => 'Júlia',
        'M-011' => 'Larissa',
        'M-012' => 'Mariana',
        'M-013' => 'Natália',
        'M-014' => 'Patrícia',
        'M-015' => 'Renata',
        'M-016' => 'Sofia',
    ];

    /** @var list<AvatarEntry>|null */
    private static ?array $cached = null;

    public static function getDefaultSlug(): string
    {
        return self::SLUG_INITIALS;
    }

    public static function isInitialsSlug(string $slug): bool
    {
        return trim($slug) === self::SLUG_INITIALS;
    }

    /**
     * Normaliza valor vindo do BD (legado default → initials).
     */
    public static function normalizeStoredSlug(string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '' || $slug === self::SLUG_LEGACY_DEFAULT) {
            return self::SLUG_INITIALS;
        }
        if (self::isInitialsSlug($slug)) {
            return self::SLUG_INITIALS;
        }
        if (self::isAllowedSlug($slug)) {
            return $slug;
        }
        return self::SLUG_INITIALS;
    }

    /**
     * Iniciais a partir de nome e sobrenome (mesma regra que alocações: ignora partículas).
     */
    public static function initialsFromNames(string $nome, string $sobrenome): string
    {
        $s = UserInitialsHelper::fromNomeSobrenome($nome, $sobrenome);
        return $s === '—' ? '?' : $s;
    }

    /**
     * Lista para select: primeiro "Iniciais", depois arquivos da pasta vivid.
     *
     * @return list<AvatarEntry>
     */
    public static function listForUi(): array
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $out = [
            [
                'slug' => self::SLUG_INITIALS,
                'url' => '',
                'label' => 'Iniciais (sem imagem)',
                'is_initial' => true,
            ],
        ];

        $dir = self::pathVividDir();
        if (!is_dir($dir) || !is_readable($dir)) {
            self::$cached = $out;
            return self::$cached;
        }

        $files = scandir($dir, SCANDIR_SORT_ASCENDING);
        if ($files === false) {
            self::$cached = $out;
            return self::$cached;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (!is_file($path)) {
                continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, self::IMAGE_EXTENSIONS, true)) {
                continue;
            }
            $base = pathinfo($file, PATHINFO_FILENAME);
            if (!self::isSafeFilenameStem($base)) {
                continue;
            }
            $slug = $base;
            $url = self::DIR_VIVID_REL . '/' . rawurlencode($file);
            $label = self::vividLabelForStem($base);
            $out[] = ['slug' => $slug, 'url' => $url, 'label' => $label];
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
        if (self::isInitialsSlug($slug)) {
            return true;
        }
        foreach (self::listForUi() as $entry) {
            if (!empty($entry['is_initial'])) {
                continue;
            }
            if ($entry['slug'] === $slug) {
                return true;
            }
        }
        return false;
    }

    /**
     * URL pública da imagem ou string vazia se for modo iniciais / desconhecido.
     */
    public static function urlForSlug(string $slug): string
    {
        $slug = trim($slug);
        if (self::isInitialsSlug($slug)) {
            return '';
        }
        foreach (self::listForUi() as $entry) {
            if (!empty($entry['is_initial'])) {
                continue;
            }
            if ($entry['slug'] === $slug && ($entry['url'] ?? '') !== '') {
                return $entry['url'];
            }
        }
        return '';
    }

    private static function pathVividDir(): string
    {
        return dirname(__DIR__, 2) . '/public' . self::DIR_VIVID_REL;
    }

    private static function isSafeFilenameStem(string $stem): bool
    {
        return $stem !== '' && (bool) preg_match('/^[a-zA-Z0-9_-]+$/', $stem);
    }

    private static function humanLabelFromStem(string $stem): string
    {
        $s = str_replace(['_', '-'], ' ', $stem);
        return $s !== '' ? $s : $stem;
    }

    private static function vividLabelForStem(string $stem): string
    {
        if (isset(self::VIVID_DISPLAY_NAMES[$stem])) {
            return self::VIVID_DISPLAY_NAMES[$stem];
        }
        return self::humanLabelFromStem($stem);
    }
}
