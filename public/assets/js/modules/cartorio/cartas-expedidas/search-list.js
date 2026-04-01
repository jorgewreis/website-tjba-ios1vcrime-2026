(function (window) {
    'use strict';

    window.CartasExpedidasSearch = window.CartasExpedidasSearch || {};
    window.CartasExpedidasSearch.initializeProcessSearch = function (config) {
        if (window.App && App.ListSearch && typeof App.ListSearch.initializeProcessSearch === 'function') {
            return App.ListSearch.initializeProcessSearch(config);
        }
        return null;
    };
})(window);
