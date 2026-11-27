<?php if ($prioridadeEditavel): ?>
<div class="ce-form-priority">
 <label for="ce-prioridade-tarefa">Prioridade da carta</label>
 <select id="ce-prioridade-tarefa" name="prioridade_tarefa">
 <?php if ($prioridadeInicial === ''): ?><option value="">Sem prioridade definida</option><?php endif; ?>
 <?php foreach (CartasExpedidasPrioridade::ROTULOS as $key=>$label): ?>
  <option value="<?= $key ?>" <?= $prioridadeInicial===$key?'selected':'' ?>><?= $label ?></option>
 <?php endforeach; ?>
 </select>
</div>
<?php else: ?>
<p class="ce-rule-scope">O campo de prioridade fica disponível após a atualização do banco.</p>
<?php endif; ?>
