const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('public/assets/js/shared/reset-link-bootstrap.js', 'utf8');
const resetService = fs.readFileSync('app/services/PasswordResetService.php', 'utf8');
const resetWrapper = fs.readFileSync('public/redefinir_senha.php', 'utf8');
const resetToken = 'synthetic-secret-token';
let replacedUrl = '';
let requestUrl = '';
let requestOptions = null;
let finalUrl = '';
let visible = false;
let failureStatus = null;
let statusInsertedInFrameParent = false;

const frameParent = {
    insertBefore(node, reference) {
        assert.equal(reference, frame, 'retry status should be inserted immediately before the iframe');
        statusInsertedInFrameParent = true;
    }
};
const frame = { parentNode: frameParent, style: { set visibility(value) { visible = value === 'visible'; } } };
const csrfMeta = { content: 'synthetic-csrf-token' };
const window = {
    location: {
        hash: '#token=' + encodeURIComponent(resetToken),
        pathname: '/redefinir_senha.php',
        search: '',
        replace(url) { finalUrl = url; }
    },
    history: { replaceState(_state, _title, url) { replacedUrl = url; } },
    fetch(url, options) {
        requestUrl = url;
        requestOptions = options;
        return Promise.resolve({ ok: true });
    }
};
const document = {
    getElementById(id) { return id === 'login-iframe' ? frame : null; },
    querySelector(selector) { return selector === 'meta[name="reset-bootstrap-csrf"]' ? csrfMeta : null; },
    createElement() { return { setAttribute(name, value) { this[name] = value; }, set textContent(value) { failureStatus = value; } }; },
    body: { insertBefore() { throw new Error('body is not the iframe parent'); } }
};

vm.runInNewContext(source, { window, document, URLSearchParams });
assert.match(resetService, /redefinir_senha\.php#token=/, 'email link must carry the credential only in its fragment');
assert.doesNotMatch(resetService, /redefinir_senha\.php\?token=/, 'email link must not send the credential in the request URL');
assert.match(resetWrapper, /reset_action.*stage_token/s, 'wrapper must accept the token only through the staging POST');
assert.match(resetWrapper, /password_reset_form_token/, 'wrapper must keep a staged token in the session');
assert.match(resetWrapper, /legacyToken[\s\S]*Location: \/redefinir_senha\.php#token=/, 'legacy query links must be migrated to a fragment redirect');
assert.ok(resetWrapper.includes("preg_match('/^[a-f0-9]{64}$/iD'"), 'legacy redirect must accept only generated token shape');
assert.doesNotMatch(resetWrapper, /echo[^;]*legacyToken/, 'legacy credential must not be reflected in the response body');
assert.doesNotMatch(fs.readFileSync('public/views/public/redefinir-senha-form.php', 'utf8'), /\$_GET\[['"]token['"]\]/, 'form must not accept credentials in the query');
assert.doesNotMatch(fs.readFileSync('public/_layout_login.php', 'utf8'), /cdn-uicons\.flaticon\.com[\s\S]{0,100}\$needsResetFragmentBootstrap/, 'reset shell must not load third-party CSS');
setImmediate(() => {
    assert.equal(replacedUrl, '/redefinir_senha.php');
    assert.equal(requestUrl, '/redefinir_senha.php');
    assert.equal(requestOptions.method, 'POST');
    assert.equal(requestOptions.cache, 'no-store');
    assert.equal(new URLSearchParams(requestOptions.body).get('token'), resetToken);
    assert.equal(finalUrl, '/redefinir_senha.php');
    assert.equal(visible, false, 'iframe should reload only after token staging succeeds');
    const failedWindow = {
        location: { hash: '#token=' + encodeURIComponent(resetToken), pathname: '/redefinir_senha.php', search: '', replace() { throw new Error('must not navigate after failure'); } },
        history: { replaceState() { throw new Error('must preserve fragment for retry'); } },
        fetch() { return Promise.reject(new Error('synthetic network failure')); }
    };
    const failedDocument = {
        getElementById(id) { return id === 'login-iframe' ? frame : null; },
        querySelector() { return csrfMeta; },
        createElement() { return { setAttribute(name, value) { this[name] = value; }, set textContent(value) { failureStatus = value; } }; },
        body: { insertBefore() { throw new Error('body is not the iframe parent'); } }
    };
    vm.runInNewContext(source, { window: failedWindow, document: failedDocument, URLSearchParams });
    setImmediate(() => {
        assert.equal(failedWindow.location.hash, '#token=' + encodeURIComponent(resetToken));
        assert.match(failureStatus, /recarregue a página/);
        assert.equal(statusInsertedInFrameParent, true, 'retry notice should use the iframe parent container');
        assert.equal(visible, true, 'failed bootstrap should reveal a retry instruction');
        process.stdout.write('PASS REI-156 fragment bootstrap and retry fallback\n');
    });
});
