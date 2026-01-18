/**
 * menu-access.js - Controle de acesso ao menu baseado em perfis
 * Responsável por: filtrar items do menu de acordo com o perfil do usuário
 */

/**
 * Mapa de perfis para seções do menu
 * Define qual seção está associada a qual perfil
 */
const PERFIL_MENU_MAP = {
    'ESTUDANTE': ['Estudantes'],
    'SERVIDOR': ['Cartório', 'Gabinete', 'Sala de Audiências'],
    'ADMIN': ['Cartório', 'Gabinete', 'Sala de Audiências', 'Estudantes', 'Admin']
};

/**
 * Busca os perfis do usuário via API
 * @returns {Promise<Array>} Array com perfis do usuário
 */
async function fetchUserPerfis() {
    try {
        const response = await fetch('/admin/api/getPerfis.php');
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const result = await response.json();

        if (result.success && Array.isArray(result.perfis)) {
            return result.perfis;
        }

        console.warn('Nenhum perfil retornado pela API');
        return [];

    } catch (err) {
        console.error('Erro ao buscar perfis do usuário:', err);
        return [];
    }
}

/**
 * Retorna as seções do menu que o usuário pode visualizar
 * @param {Array<string>} perfis - Array com perfis do usuário
 * @returns {Set<string>} Set com nomes das seções permitidas
 */
function getPermittedSections(perfis) {
    const permittedSections = new Set();

    // Dashboard está sempre visível
    permittedSections.add('Dashboard');

    // Adicionar seções baseadas em perfis
    perfis.forEach(perfil => {
        const sections = PERFIL_MENU_MAP[perfil];
        
        if (sections && Array.isArray(sections)) {
            sections.forEach(section => {
                permittedSections.add(section);
            });
        }
    });

    return permittedSections;
}

/**
 * Filtra o menu escondendo items não permitidos
 * @param {Set<string>} permittedSections - Set com seções permitidas
 */
function filterMenuByAccess(permittedSections) {
    const menuHeaders = document.querySelectorAll('.menu-header');
    
    menuHeaders.forEach(header => {
        const sectionName = header.querySelector('.menu-header-text')?.textContent?.trim();
        
        if (!sectionName) {
            return; // Skip se não houver texto
        }

        const isAllowed = permittedSections.has(sectionName);

        if (!isAllowed) {
            // Esconder o header
            header.style.display = 'none';

            // Esconder items relacionados a esta seção
            // Buscar o próximo item até encontrar outro header ou fim da lista
            let currentElement = header.nextElementSibling;

            while (currentElement && !currentElement.classList.contains('menu-header')) {
                currentElement.style.display = 'none';
                currentElement = currentElement.nextElementSibling;
            }
        }
    });
}

/**
 * Inicializa o controle de acesso ao menu
 * Chamado quando o documento está pronto
 */
async function initMenuAccess() {
    try {
        // Buscar perfis do usuário
        const perfis = await fetchUserPerfis();

        if (perfis.length === 0) {
            console.warn('Usuário sem perfis definidos');
            return;
        }

        // Obter seções permitidas
        const permittedSections = getPermittedSections(perfis);

        // Filtrar menu
        filterMenuByAccess(permittedSections);

        // Debug: Log de perfis e seções (remover em produção)
        // console.log('Perfis do usuário:', perfis);
        // console.log('Seções permitidas:', Array.from(permittedSections));

    } catch (err) {
        console.error('Erro ao inicializar controle de acesso ao menu:', err);
        // Não impedir carregamento da página em caso de erro
    }
}

// Expõe funções públicas
window.initMenuAccess = initMenuAccess;
window.fetchUserPerfis = fetchUserPerfis;
