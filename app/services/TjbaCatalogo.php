<?php
declare(strict_types=1);

/** Catálogo administrativo: não contém números ou dados de processos. */
final class TjbaCatalogo
{
    private const BASE = 'https://transparencia-service-api.tjba.jus.br/';
    private const MAX_BYTES = 2097152;

    public static function caminho(): string
    {
        return dirname(__DIR__, 2) . '/storage/catalogo-tjba.sqlite';
    }

    public static function ler(string $path): ?array
    {
        if (!is_file($path)) return null;
        try {
            $db = new PDO('sqlite:' . $path, null, null, [
                PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY,
            ]);
            $row = $db->query('SELECT dados, verificado_em FROM catalogo WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;
            $dados = json_decode($row['dados'], true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($dados['comarcas'] ?? null) || !is_array($dados['unidades'] ?? null)) return null;
            return ['dados' => $dados, 'verificado_em' => $row['verificado_em']];
        } catch (Throwable) {
            return null;
        }
    }

    /** @param null|callable(string): array $buscar Transporte injetável apenas para validação isolada. */
    public static function atualizar(string $path, ?callable $buscar = null): string
    {
        $lock = fopen($path . '.lock', 'c');
        if ($lock === false) throw new RuntimeException('Não foi possível abrir a trava do catálogo.');
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) return 'ocupado';
            $buscar ??= self::buscar(...);
            $dados = self::normalizar($buscar('comarcas'), $buscar('unidades'));
            $antigo = self::ler($path);
            // Uma resposta truncada não deve substituir o diretório inteiro.
            if ($antigo && (count($dados['comarcas']) < 0.8 * count($antigo['dados']['comarcas'])
                || count($dados['unidades']) < 0.8 * count($antigo['dados']['unidades']))) {
                throw new RuntimeException('Redução inesperada no catálogo; mantida a versão anterior.');
            }
            $alterado = !$antigo || $antigo['dados'] !== $dados;
            $tmp = tempnam(dirname($path), '.catalogo-tjba-');
            if ($tmp === false) throw new RuntimeException('Não foi possível criar a cópia temporária.');
            try {
                if (realpath(dirname($tmp)) !== realpath(dirname($path))) {
                    throw new RuntimeException('Cópia temporária fora da pasta do catálogo.');
                }
                $db = new PDO('sqlite:' . $tmp);
                $db->exec('CREATE TABLE catalogo (id INTEGER PRIMARY KEY CHECK (id = 1), dados TEXT NOT NULL, verificado_em TEXT NOT NULL)');
                $cmd = $db->prepare('INSERT INTO catalogo VALUES (1, ?, ?)');
                $cmd->execute([json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), gmdate(DATE_ATOM)]);
                unset($cmd, $db);
                if (self::ler($tmp) === null || !chmod($tmp, 0644) || !rename($tmp, $path)) {
                    throw new RuntimeException('Não foi possível publicar a nova cópia do catálogo.');
                }
            } finally {
                if (is_file($tmp)) unlink($tmp);
            }
            return $alterado ? 'atualizado' : 'sem_alteracoes';
        } finally {
            fclose($lock);
        }
    }

    private static function buscar(string $rota): array
    {
        $curl = curl_init(self::BASE . $rota);
        $raw = '';
        curl_setopt_array($curl, [
            CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 30, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$raw): int {
                if (strlen($raw) + strlen($chunk) > self::MAX_BYTES) return 0;
                $raw .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($ok !== true || $status !== 200) throw new RuntimeException('Falha ao baixar catálogo do TJBA.');
        $result = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($result)) throw new RuntimeException('Resposta inválida no catálogo do TJBA.');
        return $result;
    }

    private static function normalizar(array $comarcas, array $unidades): array
    {
        if (!array_is_list($comarcas) || !array_is_list($unidades) || count($comarcas) < 100 || count($unidades) < 400) {
            throw new RuntimeException('Listas incompletas do TJBA.');
        }
        $c = $u = [];
        foreach ($comarcas as $item) {
            $id = $item['idComarca'] ?? null;
            $nome = self::nome($item['dsComarca'] ?? null);
            if (!is_int($id) || $id <= 0 || isset($c[$id])) throw new RuntimeException('ID de comarca inválido.');
            $c[$id] = $nome;
        }
        foreach ($unidades as $item) {
            $id = $item['idUnidade'] ?? null;
            $comarca = $item['idMunicipio'] ?? null;
            $ativa = $item['flAtivo'] ?? null;
            // O catálogo publicado tem marcadores inativos sem identificador utilizável.
            if ($id === 0 && $ativa === false) continue;
            $nome = self::nome($item['dsUnidade'] ?? null);
            // O TJBA também lista unidades administrativas ou históricas sem comarca publicada.
            if (!is_int($id) || $id <= 0 || !is_bool($ativa)
                || ($comarca !== null && !is_int($comarca))) throw new RuntimeException('Unidade inválida no catálogo.');
            $registro = ['nome' => $nome, 'comarca' => $comarca, 'ativa' => $ativa];
            if (isset($u[$id]) && $u[$id] !== $registro) {
                throw new RuntimeException('Cadastro divergente para o mesmo ID de unidade.');
            }
            $u[$id] = $registro;
        }
        if (!isset($c[2146], $u[4701]) || $u[4701]['comarca'] !== 2146) {
            throw new RuntimeException('Referência conhecida de Ilhéus ausente ou divergente.');
        }
        ksort($c);
        ksort($u);
        return ['comarcas' => $c, 'unidades' => $u];
    }

    private static function nome(mixed $nome): string
    {
        if (!is_string($nome) || trim($nome) === '' || strlen($nome) > 480 || preg_match('//u', $nome) !== 1) {
            throw new RuntimeException('Nome inválido no catálogo.');
        }
        return trim($nome);
    }
}
