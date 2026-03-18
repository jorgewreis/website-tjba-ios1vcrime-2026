<?php
declare(strict_types=1);

if (!isset($timelineData) || !is_array($timelineData)) {
    return;
}
?>
<div class="row timeline-header">
    <div class="col filter">
        <span class="header-timeline">Subtarefas - Timeline</span>
    </div>
    <div class="col filter" title="<?php echo htmlspecialchars((string) ($timelineData['currentTaskTitle'] ?? ''), ENT_QUOTES); ?>">
        <?php if (!empty($timelineData['showProgress'])): ?>
            <div class="width-100 status <?php echo htmlspecialchars((string) ($timelineData['statusClass'] ?? 'status-current'), ENT_QUOTES); ?> status-with-progress" style="border: 1px solid var(--color-gray-500);">
                <span class="status-task-label"><?php echo htmlspecialchars((string) ($timelineData['statusLabel'] ?? 'Em andamento'), ENT_QUOTES); ?></span>
                <div class="status-task-progress" role="progressbar" aria-valuenow="<?php echo (int) ($timelineData['progressValue'] ?? 0); ?>" aria-valuemin="0" aria-valuemax="<?php echo (int) ($timelineData['progressMax'] ?? 0); ?>" aria-label="Progresso das subtarefas">
                    <?php foreach (($timelineData['segments'] ?? []) as $segment): ?>
                        <div class="status-task-progress-segment <?php echo htmlspecialchars((string) ($segment['class'] ?? 'pendente'), ENT_QUOTES); ?>" title="<?php echo htmlspecialchars((string) ($segment['title'] ?? ''), ENT_QUOTES); ?>"></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="width-100 status <?php echo htmlspecialchars((string) ($timelineData['statusClass'] ?? 'status-current'), ENT_QUOTES); ?>" style="border: 1px solid var(--color-gray-500);"><?php echo htmlspecialchars((string) ($timelineData['statusLabel'] ?? 'Em andamento'), ENT_QUOTES); ?></div>
        <?php endif; ?>
    </div>
</div>

<?php $cadastroRow = $timelineData['cadastro'] ?? []; ?>
<div class="row <?php echo htmlspecialchars((string) ($cadastroRow['rowClass'] ?? 'timeline finished'), ENT_QUOTES); ?>">
    <div class="row">
        <div class="circle <?php echo htmlspecialchars((string) ($cadastroRow['circleClass'] ?? 'finished'), ENT_QUOTES); ?>"></div>
        <div class="col filter subtask-status-info">
            <span class="minor-info"><?php echo htmlspecialchars((string) ($cadastroRow['minorText'] ?? ''), ENT_QUOTES); ?></span>
            <?php if (!empty($cadastroRow['majorText'])): ?>
                <span class="major-info"><?php echo htmlspecialchars((string) $cadastroRow['majorText'], ENT_QUOTES); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="col">
        <div class="col filter subtask-date">
            <span class="minor-info"><?php echo htmlspecialchars((string) ($cadastroRow['dateLabel'] ?? '-'), ENT_QUOTES); ?></span>
        </div>
    </div>
</div>

<?php foreach (($timelineData['rows'] ?? []) as $row): ?>
    <div class="row <?php echo htmlspecialchars((string) ($row['rowClass'] ?? 'timeline'), ENT_QUOTES); ?>">
        <div class="row">
            <?php if (!empty($row['circleClass'])): ?>
                <div class="circle <?php echo htmlspecialchars((string) $row['circleClass'], ENT_QUOTES); ?>"></div>
            <?php else: ?>
                <div class="circle" style="background-color: var(--color-gray-300);"></div>
            <?php endif; ?>
            <div class="col filter subtask-status-info">
                <span class="minor-info"><?php echo htmlspecialchars((string) ($row['minorText'] ?? ''), ENT_QUOTES); ?></span>
                <?php if (!empty($row['majorText'])): ?>
                    <span class="major-info"><?php echo htmlspecialchars((string) $row['majorText'], ENT_QUOTES); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="col">
            <div class="col filter subtask-date">
                <span class="minor-info"><?php echo htmlspecialchars((string) ($row['dateLabel'] ?? '-'), ENT_QUOTES); ?></span>
            </div>
        </div>
    </div>
<?php endforeach; ?>
