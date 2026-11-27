<?php
declare(strict_types=1);

/** Validates non-empty DataJud public metadata without inferring a comarca. */
final class DataJudOrgaoMetadata
{
    /** @return array{nome?:string,municipio_ibge?:string} */
    public static function fromMapped(mixed $orgao): array
    {
        if (!is_array($orgao)) return [];
        $nome = $orgao['nome'] ?? null;
        $ibge = $orgao['codigoMunicipioIBGE'] ?? null;
        $out = [];
        if (is_string($nome) && preg_match('/[\p{C}]/u', $nome) === 0) {
            $nome = trim($nome);
            if (preg_match('/^.{1,180}$/uD', $nome) === 1) $out['nome'] = $nome;
        }
        if (is_int($ibge)) $ibge = (string) $ibge;
        if (is_string($ibge) && preg_match('/^[1-9][0-9]{6}$/D', $ibge) === 1) $out['municipio_ibge'] = $ibge;
        return $out;
    }
}
