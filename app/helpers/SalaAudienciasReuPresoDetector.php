<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/GabineteReuPresoProcessoRepository.php';
require_once __DIR__ . '/../repositories/GabineteReuPresoRepository.php';
require_once __DIR__ . '/../repositories/ProcessosPartesRepository.php';

/**
 * Detecção de "réu preso" alinhada a `sala-audiencias-pauta-edit.php` (mapas + fallbacks de nome).
 */
final class SalaAudienciasReuPresoDetector
{
    /** @return array{0: array<string,bool>, 1: array<string,bool>} [ mapaNomeNormalizado, mapaAssinatura ] */
    public static function buildReusPresosMaps(PDO $pdo, string $processoId, string $numeroProcesso): array
    {
        $reusPresosMap = [];
        $reusPresosSigMap = [];

        try {
            $repoReuPreso = new GabineteReuPresoRepository($pdo);
            $nomesGlobais = $repoReuPreso->listAtivosNomes();
            foreach ($nomesGlobais as $nomeGlobal) {
                self::addNomeToReuMap((string) $nomeGlobal, $reusPresosMap, $reusPresosSigMap);
            }
        } catch (Throwable $e) {
            // ignora
        }

        if ($processoId !== '') {
            try {
                $repoReuPresoProcesso = new GabineteReuPresoProcessoRepository($pdo);
                $nomesReusPresos = $repoReuPresoProcesso->listReusPresosNomesByProcessoId($processoId);
                foreach ($nomesReusPresos as $nomeReuPreso) {
                    self::addNomeToReuMap((string) $nomeReuPreso, $reusPresosMap, $reusPresosSigMap);
                }
                if ($numeroProcesso !== '') {
                    $nomesFallback = $repoReuPresoProcesso->listReusPresosNomesByNumeroProcesso($numeroProcesso);
                    foreach ($nomesFallback as $nomeReuPreso) {
                        self::addNomeToReuMap((string) $nomeReuPreso, $reusPresosMap, $reusPresosSigMap);
                    }
                }
            } catch (Throwable $e) {
                // ignora
            }
        }

        if ($processoId === '' && $numeroProcesso !== '') {
            try {
                $repoReuPresoProcesso = new GabineteReuPresoProcessoRepository($pdo);
                $nomesFallback = $repoReuPresoProcesso->listReusPresosNomesByNumeroProcesso($numeroProcesso);
                foreach ($nomesFallback as $nomeReuPreso) {
                    self::addNomeToReuMap((string) $nomeReuPreso, $reusPresosMap, $reusPresosSigMap);
                }
            } catch (Throwable $e) {
                // ignora
            }
        }

        return [$reusPresosMap, $reusPresosSigMap];
    }

    /**
     * @param list<array<string,mixed>> $partes Linhas de sala_audiencias_pauta_parte (ativo).
     */
    public static function audienciaTemReuPreso(PDO $pdo, string $processoId, string $numeroProcesso, array $partes): bool
    {
        [$map, $sigMap] = self::buildReusPresosMaps($pdo, $processoId, $numeroProcesso);
        if ($map === [] && $sigMap === []) {
            return false;
        }

        foreach ($partes as $parte) {
            if ((string) ($parte['tipo_parte'] ?? '') !== 'reu') {
                continue;
            }
            $nomeParteRaw = trim((string) ($parte['nome_parte'] ?? ''));
            if ($nomeParteRaw === '') {
                continue;
            }
            if (self::nomeReuCorrespondeReuPreso($nomeParteRaw, $map, $sigMap)) {
                return true;
            }
        }

        return false;
    }

    /** Réu preso no âmbito do processo (vínculos em `processos_partes` + cadastro gabinete). */
    public static function processoTemReuPreso(PDO $pdo, string $processoId, string $numeroProcesso): bool
    {
        $processoId = trim($processoId);
        if ($processoId === '') {
            return false;
        }

        $repo = new ProcessosPartesRepository($pdo);
        $acusados = $repo->findAcusadosByProcessoId($processoId);
        if ($acusados === []) {
            return false;
        }

        [$map, $sigMap] = self::buildReusPresosMaps($pdo, $processoId, trim($numeroProcesso));
        if ($map === [] && $sigMap === []) {
            return false;
        }

        foreach ($acusados as $row) {
            $nomeParteRaw = trim((string) ($row['nome_completo'] ?? ''));
            if ($nomeParteRaw === '') {
                continue;
            }
            if (self::nomeReuCorrespondeReuPreso($nomeParteRaw, $map, $sigMap)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,bool> $reusPresosMap */
    /** @param array<string,bool> $reusPresosSigMap */
    public static function nomeReuCorrespondeReuPreso(string $nomeParteRaw, array $reusPresosMap, array $reusPresosSigMap): bool
    {
        $nomeParteAtualKey = self::normalizeNomeParte($nomeParteRaw);
        $nomeParteAtualSig = $nomeParteAtualKey !== '' ? self::signatureNomeParte($nomeParteAtualKey) : '';

        $isReuPreso = $nomeParteAtualKey !== ''
            && (isset($reusPresosMap[$nomeParteAtualKey])
                || ($nomeParteAtualSig !== '' && isset($reusPresosSigMap[$nomeParteAtualSig])));

        if (!$isReuPreso && $nomeParteAtualKey !== '') {
            $wordsReu = array_values(array_filter(explode(' ', $nomeParteAtualKey), static function ($w) {
                return $w !== '';
            }));
            if ($wordsReu !== []) {
                foreach ($reusPresosMap as $k => $_) {
                    $wordsPreso = array_values(array_filter(explode(' ', (string) $k), static function ($w) {
                        return $w !== '';
                    }));
                    if ($wordsPreso === []) {
                        continue;
                    }
                    $allWordsExist = true;
                    foreach ($wordsReu as $w) {
                        if ($w === '') {
                            continue;
                        }
                        if (!in_array($w, $wordsPreso, true) && strpos((string) $k, (string) $w) === false) {
                            $allWordsExist = false;
                            break;
                        }
                    }
                    if ($allWordsExist) {
                        $isReuPreso = true;
                        break;
                    }
                }
            }

            if (!$isReuPreso && function_exists('similar_text')) {
                foreach ($reusPresosMap as $k => $_) {
                    $presoKey = (string) $k;
                    if ($presoKey === '') {
                        continue;
                    }
                    $percent = 0.0;
                    similar_text($nomeParteAtualKey, $presoKey, $percent);
                    if ($percent >= 90) {
                        $isReuPreso = true;
                        break;
                    }
                }
            }
        }

        return $isReuPreso;
    }

    private static function normalizeNomeParte(string $s): string
    {
        $s = trim($s);
        $s = str_replace("\xC2\xA0", ' ', $s);
        $s = preg_replace('/\p{Z}+/u', ' ', $s) ?: $s;
        $s = preg_replace('/[[:space:]]+/u', ' ', $s) ?: $s;
        $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);

        try {
            if (class_exists('Normalizer', false)) {
                $s = \Normalizer::normalize($s, \Normalizer::FORM_D);
                $s = preg_replace('/\p{Mn}/u', '', $s) ?: $s;
            }
        } catch (Throwable $e) {
            // ignora
        }

        if (function_exists('iconv')) {
            $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($trans !== false && $trans !== null) {
                $s = $trans;
            }
        }

        $s = preg_replace('/[^\p{L}\p{N} ]/u', '', $s) ?: $s;
        $s = preg_replace('/[[:space:]]+/u', ' ', $s) ?: $s;

        return trim($s);
    }

    private static function normalizeNomeParteFallback(string $s): string
    {
        $s = trim(str_replace("\xC2\xA0", ' ', $s));
        $s = preg_replace('/\s+/', ' ', $s) ?: $s;
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9 ]+/', '', $s) ?: $s;
        $s = preg_replace('/\s+/', ' ', $s) ?: $s;

        return trim($s);
    }

    private static function signatureNomeParte(string $normalized): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized) ?: '';
    }

    /**
     * @param array<string,bool> $map
     * @param array<string,bool> $sigMap
     */
    private static function addNomeToReuMap(string $nome, array &$map, array &$sigMap): void
    {
        $key = self::normalizeNomeParte($nome);
        if ($key === '') {
            $key = self::normalizeNomeParteFallback($nome);
        }
        if ($key === '') {
            return;
        }
        $map[$key] = true;
        $sig = self::signatureNomeParte($key);
        if ($sig === '') {
            $sig = preg_replace('/[^a-z0-9]+/', '', strtolower($key)) ?: '';
        }
        if ($sig !== '') {
            $sigMap[$sig] = true;
        }
    }
}
