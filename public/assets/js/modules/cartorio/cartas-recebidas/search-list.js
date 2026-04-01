(function (window) {
    'use strict';

    window.CartasRecebidasSearch = window.CartasRecebidasSearch || {};
    window.CartasRecebidasSearch.initializeProcessSearch = function (config) {
        if (window.App && App.ListSearch && typeof App.ListSearch.initializeProcessSearch === 'function') {
            return App.ListSearch.initializeProcessSearch(config);
        }
        return null;
    };
})(window);
