<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/ParteDocumentoHelper.php';

/** @var string $documentoTipoSelecionado */
$documentoTipoSelecionado = isset($documentoTipoSelecionado) ? ParteDocumentoHelper::normalizarTipo((string) $documentoTipoSelecionado) : '';
?>
<option value="">Selecione</option>
<?php foreach (ParteDocumentoHelper::TIPOS as $tipoDoc): ?>
<option value="<?php echo htmlspecialchars($tipoDoc); ?>"<?php echo $documentoTipoSelecionado === $tipoDoc ? ' selected' : ''; ?>><?php echo htmlspecialchars($tipoDoc); ?></option>
<?php endforeach; ?>
