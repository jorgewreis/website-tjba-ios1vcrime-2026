<?php
declare(strict_types=1);

/**
 * Localidades listadas pela Corregedoria do TJBA como atendidas por Central de
 * Mandados (https://www.tjba.jus.br/corregedoria/central-de-mandados/).
 * Lista conferida em 13/10/2026.
 */
final class CentralMandadosCcm
{
    private const UF = 'BA';
    public const MESSAGE = 'A comarca já possui Central de Mandados (CCM). Deve ser expedido Mandado em vez de Carta Precatória.';

    private const CIDADES = [
        'Alagoinhas',
        'Amargosa',
        'Amélia Rodrigues',
        'Barra',
        'Barra do Choça',
        'Barreiras',
        'Bom Jesus da Lapa',
        'Brumado',
        'Caculé',
        'Caetité',
        'Camacan',
        'Camaçari',
        'Campo Formoso',
        'Canavieiras',
        'Candeias',
        'Catu',
        'Cícero Dantas',
        'Cipó',
        'Coaraci',
        'Conceição do Coité',
        'Cruz das Almas',
        "Dias d'Ávila",
        'Entre Rios',
        'Euclides da Cunha',
        'Eunápolis',
        'Feira de Santana',
        'Formosa do Rio Preto',
        'Gandu',
        'Guanambi',
        'Ibotirama',
        'Iguaí',
        'Ilhéus',
        'Inhambupe',
        'Ipiaú',
        'Ipirá',
        'Irecê',
        'Itaberaba',
        'Itabuna',
        'Itamaraju',
        'Itambé',
        'Itaparica',
        'Itapetinga',
        'Itapicuru',
        'Ituberá',
        'Jacobina',
        'Jequié',
        'Juazeiro',
        'Lauro de Freitas',
        'Livramento de Nossa Senhora',
        'Luís Eduardo Magalhães',
        'Mata de São João',
        'Nazaré',
        'Nova Soure',
        'Nova Viçosa',
        'Olindina',
        'Paulo Afonso',
        'Piritiba',
        'Pojuca',
        'Porto Seguro',
        'Remanso',
        'Riachão do Jacuípe',
        'Riacho de Santana',
        'Ribeira do Pombal',
        'Rio Real',
        'Salvador',
        'Santa Maria da Vitória',
        'Santo Amaro',
        'Santo Antônio de Jesus',
        'Santo Estêvão',
        'São Desidério',
        'São Francisco do Conde',
        'São Sebastião do Passé',
        'Senhor do Bonfim',
        'Serrinha',
        'Simões Filho',
        'Teixeira de Freitas',
        'Terra Nova',
        'Ubatã',
        'Valença',
        'Valente',
        'Vitória da Conquista',
    ];

    public static function contains(string $cidade, string $uf): bool
    {
        if (strtoupper(trim($uf)) !== self::UF) {
            return false;
        }

        $cidadeNormalizada = self::normalize($cidade);
        if ($cidadeNormalizada === '') {
            return false;
        }

        foreach (self::CIDADES as $cidadeCcm) {
            if (self::normalize($cidadeCcm) === $cidadeNormalizada) {
                return true;
            }
        }

        return false;
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $ascii = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) : false;
        if (is_string($ascii)) {
            $value = $ascii;
        }

        return preg_replace('/[^a-z0-9]+/', '', strtolower($value)) ?? '';
    }
}
