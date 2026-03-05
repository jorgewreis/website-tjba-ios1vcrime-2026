(function (global) {
    const DEFAULT_EXCEPTIONS = ['de', 'da', 'do', 'das', 'dos', 'e', 'em', 'no', 'na'];

    function onlyLetters(value) {
        return (value || '').replace(/[^A-Za-zÀ-ú\s]+/g, ' ').trim();
    }

    function capitalizeToken(token, index, exceptions) {
        const lower = token.toLowerCase();
        if (index > 0 && exceptions.includes(lower)) {
            return lower;
        }
        return lower.charAt(0).toUpperCase() + lower.slice(1);
    }

    function formatProperName(value, maxLength = 50, exceptions = DEFAULT_EXCEPTIONS) {
        if (!value) {
            return '';
        }
        const clean = onlyLetters(value).split(/\s+/).filter(Boolean);
        const transformed = clean.map((token, index) => capitalizeToken(token, index, exceptions));
        let formatted = transformed.join(' ');
        if (formatted.length > maxLength) {
            formatted = formatted.slice(0, maxLength).trim();
        }
        return formatted;
    }

    function ensureGlobal() {
        if (typeof global.TextFormatter === 'undefined') {
            global.TextFormatter = {};
        }
        return global.TextFormatter;
    }

    const exported = ensureGlobal();
    exported.formatProperName = formatProperName;
})(typeof window !== 'undefined' ? window : global);
