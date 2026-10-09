(function (global) {
    'use strict';

    const App = global.App || (global.App = {});

    const defaultConfig = {
        minSearchDigits: 3,
        debounceDelay: 1000,
        routeParamKey: 'page',
        /** Nome do parâmetro GET usado pelo campo principal de busca (ex.: `processo`, `oficio`). */
        searchParamKey: 'processo'
    };

    function normalizeValue(value) {
        return value === null || value === undefined ? '' : String(value);
    }

    function setParam(params, key, value) {
        const trimmed = value.trim();
        if (trimmed === '') {
            params.delete(key);
            return;
        }
        params.set(key, trimmed);
    }

    function initializeProcessSearch(config) {
        const cfg = Object.assign({}, defaultConfig, config);
        const input = document.getElementById(cfg.inputId);
        if (!input) {
            return null;
        }

        const filterElements = (cfg.filters || [])
            .map(function (item) {
                if (!item || !item.id || !item.param) {
                    return null;
                }
                const el = document.getElementById(item.id);
                if (!el) {
                    return null;
                }
                return { element: el, param: item.param };
            })
            .filter(Boolean);

        if (filterElements.length === 0) {
            return null;
        }

        const searchDelay = Number(cfg.debounceDelay) || defaultConfig.debounceDelay;
        const minDigits = Math.max(1, Number(cfg.minSearchDigits) || defaultConfig.minSearchDigits);
        const routeParamKey = cfg.routeParamKey || defaultConfig.routeParamKey;
        const searchParamKey = normalizeValue(cfg.searchParamKey || defaultConfig.searchParamKey) || 'processo';
        const routeParamValue = new URL(global.location.href).searchParams.get(routeParamKey);
        const initialValue = normalizeValue(cfg.initialProcessFilter || input.value);
        let hasActiveProcessFilter = initialValue.trim().length >= minDigits;
        let searchTimeout = null;

        const applyFilters = function () {
            const url = new URL(global.location.href);
            const params = url.searchParams;

            if (searchTimeout !== null) {
                clearTimeout(searchTimeout);
                searchTimeout = null;
            }

            filterElements.forEach(function (entry) {
                setParam(params, entry.param, normalizeValue(entry.element.value));
            });

            const searchValue = normalizeValue(input.value).trim();
            if (searchValue.length >= minDigits) {
                params.set(searchParamKey, searchValue);
            } else {
                params.delete(searchParamKey);
            }

            setParam(params, 'items_per_page', '15');
            if (routeParamValue) {
                params.set(routeParamKey, routeParamValue);
            }
            params.set('page_number', '1');

            global.location.href = url.toString();
        };

        filterElements.forEach(function (entry) {
            entry.element.addEventListener('change', applyFilters);
        });
        itemsSelect.addEventListener('change', applyFilters);

        input.addEventListener('input', function () {
            const value = normalizeValue(input.value).trim();
            if (searchTimeout !== null) {
                clearTimeout(searchTimeout);
                searchTimeout = null;
            }
            const shouldTriggerSearch = value.length >= minDigits || (hasActiveProcessFilter && value.length < minDigits);
            if (!shouldTriggerSearch) {
                return;
            }
            hasActiveProcessFilter = value.length >= minDigits;
            searchTimeout = setTimeout(applyFilters, searchDelay);
        });

        return {
            applyFilters: applyFilters
        };
    }

    App.ListSearch = {
        initializeProcessSearch: initializeProcessSearch
    };
})(window);
