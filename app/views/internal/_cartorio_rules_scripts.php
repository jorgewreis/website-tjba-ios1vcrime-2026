<?php
declare(strict_types=1);

/**
 * Scripts comuns: toasts, Voltar (window.location), sync Salvar / Remover alocação.
 *
 * Variáveis esperadas:
 * - $successMessage (string)
 * - $errors (array)
 * - $voltarPage (string) — valor de page= na URL (ex.: guias), sem ? nem frame.php
 */
$successMessage = (string) ($successMessage ?? '');
$errors = isset($errors) && is_array($errors) ? $errors : [];
$voltarPage = trim((string) ($voltarPage ?? ''));
$voltarHref = $voltarPage !== '' ? '/frame.php?page=' . rawurlencode($voltarPage) : '/frame.php';
?>
    <script>
        (() => {
            const successMessage = <?php echo json_encode($successMessage, JSON_UNESCAPED_UNICODE); ?>;
            const errors = <?php echo json_encode(array_values($errors), JSON_UNESCAPED_UNICODE); ?>;
            if (successMessage && typeof showToast === 'function') {
                showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: successMessage });
            }
            if (Array.isArray(errors) && errors.length && typeof showToast === 'function') {
                errors.forEach((message) => showToast({ type: 'error', variant: 'full', title: 'Erro', message }));
            }
        })();

        (function () {
            const btn = document.getElementById('voltar-btn');
            if (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    window.location.href = <?php echo json_encode($voltarHref, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
                }, { capture: true });
            }
        })();

        (() => {
            const saveBtn = document.getElementById('salvar-btn');
            const clearBtn = document.getElementById('remover-alocacao-btn');
            const select = document.querySelector('select[name="id_user"]');
            if (!saveBtn || !select) return;

            const originalId = (select.getAttribute('data-original-user-id') || '').trim();

            const syncSaveButton = () => {
                const currentId = (select.value || '').trim();
                const changed = currentId !== originalId;

                if (changed) saveBtn.removeAttribute('disabled');
                else saveBtn.setAttribute('disabled', '');

                if (clearBtn) {
                    if (originalId !== '') clearBtn.removeAttribute('disabled');
                    else clearBtn.setAttribute('disabled', '');
                }
            };

            select.addEventListener('change', syncSaveButton);
            syncSaveButton();
        })();
    </script>
