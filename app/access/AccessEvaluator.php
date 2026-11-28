<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/UserAccessHelper.php';
require_once __DIR__ . '/ProfileNormalizer.php';
require_once __DIR__ . '/PageAccessRegistry.php';
require_once __DIR__ . '/AccessAreas.php';
require_once __DIR__ . '/AccessPolicyVersion.php';

final class AccessEvaluator
{
    /**
     * @param array<int, mixed> $rawPerfils
     */
    public static function canAccessPage(string $page, array $rawPerfils): bool
    {
        $norm = ProfileNormalizer::normalize($rawPerfils);
        $isExterno = UserAccessHelper::isUsuarioExterno($rawPerfils);
        $elevated = ProfileNormalizer::isElevated($norm);

        if ($page === 'dashboardexterno') {
            return $isExterno;
        }

        if ($isExterno) {
            return self::externoCanAccessPage($page, $norm);
        }

        $rule = PageAccessRegistry::ruleForPage($page);
        if ($rule['kind'] === 'deny') {
            return false;
        }

        if ($elevated) {
            return true;
        }

        // O dashboard também atende usuários internos sem perfil composto de setor.
        if (in_array($page, ['dashboard', 'dashboard2'], true)
            && array_intersect($norm, ['SERVIDOR', 'ESTAGIARIO', 'CARTORIO', 'GABINETE', 'AUDIENCIA'])) {
            return true;
        }

        return self::evaluateRuleForInternal($rule, $norm);
    }

    /**
     * @param list<string> $norm
     */
    private static function externoCanAccessPage(string $page, array $norm): bool
    {
        $temEstudante = ProfileNormalizer::hasEstudante($norm);
        $temConsultor = ProfileNormalizer::hasConsultor($norm);

        if ($page === 'alterardados') {
            return true;
        }
        if (($page === 'estudapauta' || $page === 'estudapainel' || $page === 'estudaata') && $temEstudante) {
            return true;
        }
        if ($page === 'consultapartes' || $page === 'instituicoespublicas' || $page === 'partesinstituicoeslist') {
            return $temConsultor;
        }
        return false;
    }

    /**
     * @param array<string, mixed> $rule
     * @param list<string> $norm
     */
    private static function evaluateRuleForInternal(array $rule, array $norm): bool
    {
        $kind = $rule['kind'];

        switch ($kind) {
            case 'all_internal':
                return ProfileNormalizer::hasAnyCargoAreaComposite($norm);

            case 'area_a':
                $area = (string) ($rule['area'] ?? '');
                return ProfileNormalizer::hasCompositeForArea($norm, $area);

            case 'exact':
                /** @var list<string> $profiles */
                $profiles = $rule['profiles'] ?? [];
                return ProfileNormalizer::hasAnyExact($norm, $profiles);

            case 'gestao':
                return false;

            case 'consultas_partes':
                return ProfileNormalizer::hasAnyCargoAreaComposite($norm);

            case 'consultas_processos':
                return ProfileNormalizer::hasAnyCargoAreaComposite($norm);

            case 'externo_only':
                return false;

            case 'estudantes_readonly':
                return in_array('ADMIN', $norm, true);

            default:
                return false;
        }
    }

    /**
     * Seções do menu lateral (nomes como em MENU_SECTIONS do home.php).
     *
     * @param array<int, mixed> $rawPerfils
     * @return list<string>
     */
    public static function permittedMenuSections(array $rawPerfils): array
    {
        $norm = ProfileNormalizer::normalize($rawPerfils);
        $sections = [];

        if (UserAccessHelper::isUsuarioExterno($rawPerfils)) {
            if (ProfileNormalizer::hasEstudante($norm)) {
                return ['Consultas', 'Estudantes'];
            }

            return ['Consultas'];
        }

        if (ProfileNormalizer::isElevated($norm)) {
            $com = ['Cartório', 'Gabinete', 'Sala de Audiências'];
            if (in_array('ADMIN', $norm, true)) {
                $com[] = 'Estudantes';
            }
            $com[] = 'Consultas';
            $com[] = 'Audiências';
            $com[] = 'Admin';

            return $com;
        }

        foreach ($norm as $p) {
            if (preg_match('/^(ESTAGIARIO|SERVIDOR)-(CARTORIO|GABINETE|AUDIENCIAS)$/', $p, $m) === 1) {
                if ($m[2] === 'CARTORIO') {
                    $sections[] = 'Cartório';
                } elseif ($m[2] === 'GABINETE') {
                    $sections[] = 'Gabinete';
                } else {
                    $sections[] = 'Sala de Audiências';
                }
            }
        }

        if (ProfileNormalizer::hasAnyCargoAreaComposite($norm)) {
            $sections[] = 'Consultas';
        }

        $unique = array_values(array_unique($sections));
        $order = ['Cartório', 'Gabinete', 'Sala de Audiências', 'Estudantes', 'Consultas', 'Admin'];
        $ordered = [];
        foreach ($order as $label) {
            if (in_array($label, $unique, true)) {
                $ordered[] = $label;
            }
        }
        return $ordered;
    }
}
