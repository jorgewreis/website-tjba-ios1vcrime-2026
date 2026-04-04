<?php
declare(strict_types=1);

require_once __DIR__ . '/AccessAreas.php';

/**
 * Uma entrada por chave de página (parâmetro frame.php?page=).
 *
 * kind:
 * - area_a: modo A — qualquer *-{area} (ESTAGIARIO ou SERVIDOR)
 * - exact: modo B — pelo menos um dos perfis listados (interseção exata)
 * - all_internal: usuário interno com pelo menos um composto OU elevado
 * - gestao: só ADMIN / CHEFIA
 * - consultas_cep | consultas_partes | consultas_processos: regras globais de consulta
 * - externo_only: apenas fluxo externo (dashboard externo)
 * - deny: negar (página desconhecida na política)
 */
final class PageAccessRegistry
{
    /**
     * Páginas com modo B (perfil exato). Demais rotas de Cartório/Gabinete/Audiências usam modo A via area_a.
     * Ajuste esta lista conforme a matriz em docs/access/.
     *
     * @var array<string, list<string>>
     */
    private const EXACT_PAGE_PROFILES = [
        // Exemplo: 'produtividade' => ['SERVIDOR-CARTORIO'],
    ];

    public static function ruleForPage(string $page): array
    {
        if (isset(self::EXACT_PAGE_PROFILES[$page])) {
            return ['kind' => 'exact', 'profiles' => self::EXACT_PAGE_PROFILES[$page]];
        }

        static $areaCartorio = ['kind' => 'area_a', 'area' => AccessAreas::CARTORIO];
        static $areaGabinete = ['kind' => 'area_a', 'area' => AccessAreas::GABINETE];
        static $areaAudiencias = ['kind' => 'area_a', 'area' => AccessAreas::AUDIENCIAS];

        $map = [
            'dashboard' => ['kind' => 'all_internal'],
            'dashboardexterno' => ['kind' => 'externo_only'],
            'consultacep' => ['kind' => 'consultas_cep'],

            'cejusc' => $areaCartorio,
            'cejuscedit' => $areaCartorio,
            'cejusccreate' => $areaCartorio,
            'ceapa' => $areaCartorio,
            'ceapaedit' => $areaCartorio,
            'ceapacreate' => $areaCartorio,
            'ceaparules' => $areaCartorio,
            'cejuscrules' => $areaCartorio,
            'editais' => $areaCartorio,
            'editaisedit' => $areaCartorio,
            'editaiscreate' => $areaCartorio,
            'editaisrules' => $areaCartorio,
            'guias' => $areaCartorio,
            'guiasedit' => $areaCartorio,
            'guiascreate' => $areaCartorio,
            'guiasrules' => $areaCartorio,
            'medidasprotetivas' => $areaCartorio,
            'medidasprotetivasedit' => $areaCartorio,
            'medidasprotetivascreate' => $areaCartorio,
            'medidasprotetivasrules' => $areaCartorio,
            'oficioscartorio' => $areaCartorio,
            'oficioscartorioedit' => $areaCartorio,
            'oficiosrules' => $areaCartorio,
            'cartasexpedidas' => $areaCartorio,
            'cartasexpedidasedit' => $areaCartorio,
            'cartasexpedidascreate' => $areaCartorio,
            'cartasexpedidasrules' => $areaCartorio,
            'cartasrecebidas' => $areaCartorio,
            'cartasrecebidasedit' => $areaCartorio,
            'cartasrecebidascreate' => $areaCartorio,
            'cartasrecebidasrules' => $areaCartorio,
            'produtividade' => ['kind' => 'all_internal'],
            'produtividaderegras' => ['kind' => 'gestao'],
            'produtividaderegrascreate' => ['kind' => 'gestao'],
            'produtividaderegrasedit' => ['kind' => 'gestao'],
            'produtividademetas' => ['kind' => 'gestao'],
            'produtividademetascreate' => ['kind' => 'gestao'],
            'produtividademetasedit' => ['kind' => 'gestao'],
            'produtividadebadges' => ['kind' => 'gestao'],
            'produtividadebadgescreate' => ['kind' => 'gestao'],
            'produtividadebadgesedit' => ['kind' => 'gestao'],

            'oficiosgabinete' => $areaGabinete,
            'oficiosgabineteedit' => $areaGabinete,
            'monitoramentogabinete' => $areaGabinete,
            'monitoramentogabinetecreate' => $areaGabinete,
            'monitoramentogabineteedit' => $areaGabinete,
            'salapauta' => $areaAudiencias,
            'salaata' => $areaAudiencias,
            'salapainel' => $areaAudiencias,
            'salapaineldia' => $areaAudiencias,
            'estudapauta' => ['kind' => 'estudantes_readonly'],
            'estudaata' => ['kind' => 'estudantes_readonly'],
            'estudapainel' => ['kind' => 'estudantes_readonly'],
            'salapautacreate' => $areaAudiencias,
            'salapautaedit' => $areaAudiencias,
            'salapautaparteedit' => $areaAudiencias,
            'reuspresos' => $areaGabinete,
            'reuspresoscreate' => $areaGabinete,
            'reuspresosedit' => $areaGabinete,

            'notificacoes' => ['kind' => 'all_internal'],
            'configuracoes' => ['kind' => 'all_internal'],
            'documentos' => ['kind' => 'all_internal'],
            'feriadoslist' => ['kind' => 'all_internal'],
            'alterardados' => ['kind' => 'all_internal'],

            'usuarios' => ['kind' => 'gestao'],
            'usuarioslist' => ['kind' => 'gestao'],
            'usuarioscreate' => ['kind' => 'gestao'],
            'usuariosedit' => ['kind' => 'gestao'],
            'feriadoscreate' => ['kind' => 'gestao'],
            'feriadosedit' => ['kind' => 'gestao'],

            'consultapartes' => ['kind' => 'consultas_partes'],
            'instituicoespublicas' => ['kind' => 'consultas_partes'],
            'partesinstituicoeslist' => ['kind' => 'consultas_partes'],
            'consultaprocessos' => ['kind' => 'consultas_processos'],
            'consultaprocessosedit' => ['kind' => 'consultas_processos'],

            'consultapartescreate' => $areaCartorio,
            'consultapartesedit' => $areaCartorio,
            'instituicoespublicascreate' => $areaCartorio,
            'instituicoespublicasedit' => $areaCartorio,
            'processospartescreate' => $areaCartorio,
            'processosparteslist' => $areaCartorio,
            'processospartesedit' => $areaCartorio,
            'partesinstituicoescreate' => $areaCartorio,
            'partesinstituicoesedit' => $areaCartorio,

            '401' => ['kind' => 'all_internal'],
            '403' => ['kind' => 'all_internal'],
            '404' => ['kind' => 'all_internal'],
            '500' => ['kind' => 'all_internal'],
            '503' => ['kind' => 'all_internal'],
        ];

        return $map[$page] ?? ['kind' => 'deny'];
    }
}
