<?php
require_once __DIR__ . '/config/db.php';
require_login();

$userId = current_user_id();
$pageTitle = 'Cartera';
$activePage = 'cartera.php';
$editItem = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM cartera WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        flash('success', 'Registro de cartera eliminado.');
        redirect('cartera.php');
    }

    $data = [
        'persona' => trim($_POST['persona'] ?? ''),
        'concepto' => trim($_POST['concepto'] ?? ''),
        'monto_total' => (float) ($_POST['monto_total'] ?? 0),
        'monto_cobrado' => (float) ($_POST['monto_cobrado'] ?? 0),
        'fecha_prestamo' => $_POST['fecha_prestamo'] ?? date('Y-m-d'),
        'fecha_limite' => $_POST['fecha_limite'] ?: null,
        'estado' => $_POST['estado'] ?? 'pendiente',
        'notas' => trim($_POST['notas'] ?? ''),
    ];
    $id = (int) ($_POST['id'] ?? 0);

    $errors = [];
    if ($data['persona'] === '') {
        $errors[] = 'La persona o empresa es obligatoria.';
    }
    if ($data['concepto'] === '') {
        $errors[] = 'El concepto es obligatorio.';
    }
    if ($data['monto_total'] <= 0) {
        $errors[] = 'El monto prestado debe ser mayor a cero.';
    }
    if ($data['monto_cobrado'] < 0) {
        $errors[] = 'El monto cobrado no puede ser negativo.';
    }
    if ($data['monto_cobrado'] > $data['monto_total']) {
        $errors[] = 'El monto cobrado no puede superar el monto total prestado.';
    }

    if ($errors) {
        flash('error', implode(' ', $errors));
    } else {
        upsert_cartera($pdo, $userId, $data, $id ?: null);
        flash('success', $id ? 'Cartera actualizada.' : 'Cartera registrada correctamente.');
    }

    redirect('cartera.php');
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM cartera WHERE id = ? AND user_id = ?');
    $stmt->execute([(int) $_GET['edit'], $userId]);
    $editItem = $stmt->fetch();
}

$statusFilter = $_GET['estado'] ?? 'todas';
$sql = "SELECT *, (monto_total - monto_cobrado) AS saldo_pendiente FROM cartera WHERE user_id = :user_id";
$params = ['user_id' => $userId];
if (in_array($statusFilter, ['pendiente', 'cobrada', 'vencida'], true)) {
    $sql .= ' AND estado = :estado';
    $params['estado'] = $statusFilter;
}
$sql .= " ORDER BY fecha_limite IS NULL, fecha_limite ASC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

$summaryStmt = $pdo->prepare('SELECT COALESCE(SUM(monto_total), 0) AS total_prestado, COALESCE(SUM(monto_cobrado), 0) AS total_cobrado, COALESCE(SUM(monto_total - monto_cobrado), 0) AS total_pendiente FROM cartera WHERE user_id = ?');
$summaryStmt->execute([$userId]);
$summary = $summaryStmt->fetch();

include __DIR__ . '/includes/header.php';
?>
<section class="grid-kpis three-up">
    <article class="kpi-card card">
        <span>Total prestado</span>
        <strong><?php echo e(format_currency((float) $summary['total_prestado'])); ?></strong>
    </article>
    <article class="kpi-card card">
        <span>Total cobrado</span>
        <strong class="text-success"><?php echo e(format_currency((float) $summary['total_cobrado'])); ?></strong>
    </article>
    <article class="kpi-card card">
        <span>Total pendiente</span>
        <strong class="text-warning"><?php echo e(format_currency((float) $summary['total_pendiente'])); ?></strong>
    </article>
</section>

<section class="content-grid split-main">
    <article class="card">
        <div class="section-head compact">
            <div>
                <h2><?php echo $editItem ? 'Editar cartera' : 'Nueva cartera'; ?></h2>
                <p>Registra dinero prestado y lo que sigue pendiente por cobrar.</p>
            </div>
        </div>
        <form method="post" class="stack-form">
            <input type="hidden" name="id" value="<?php echo e((string) ($editItem['id'] ?? '')); ?>">
            <label>
                <span>Persona o empresa</span>
                <input type="text" name="persona" required value="<?php echo e($editItem['persona'] ?? ''); ?>">
            </label>
            <label>
                <span>Concepto</span>
                <input type="text" name="concepto" required value="<?php echo e($editItem['concepto'] ?? ''); ?>">
            </label>
            <div class="form-row two-columns">
                <label>
                    <span>Monto prestado</span>
                    <input type="number" step="0.01" min="0" name="monto_total" required value="<?php echo e((string) ($editItem['monto_total'] ?? '')); ?>">
                </label>
                <label>
                    <span>Monto cobrado</span>
                    <input type="number" step="0.01" min="0" name="monto_cobrado" value="<?php echo e((string) ($editItem['monto_cobrado'] ?? '0')); ?>">
                </label>
            </div>
            <div class="form-row two-columns">
                <label>
                    <span>Fecha del préstamo</span>
                    <input type="date" name="fecha_prestamo" required value="<?php echo e($editItem['fecha_prestamo'] ?? date('Y-m-d')); ?>">
                </label>
                <label>
                    <span>Fecha límite</span>
                    <input type="date" name="fecha_limite" value="<?php echo e($editItem['fecha_limite'] ?? ''); ?>">
                </label>
            </div>
            <label>
                <span>Estado</span>
                <select name="estado">
                    <option value="pendiente" <?php echo (($editItem['estado'] ?? 'pendiente') === 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                    <option value="vencida" <?php echo (($editItem['estado'] ?? '') === 'vencida') ? 'selected' : ''; ?>>Vencida</option>
                    <option value="cobrada" <?php echo (($editItem['estado'] ?? '') === 'cobrada') ? 'selected' : ''; ?>>Cobrada</option>
                </select>
            </label>
            <label>
                <span>Notas</span>
                <textarea name="notas" rows="4"><?php echo e($editItem['notas'] ?? ''); ?></textarea>
            </label>
            <button class="btn btn-primary" type="submit"><?php echo $editItem ? 'Guardar cambios' : 'Registrar cartera'; ?></button>
        </form>
    </article>

    <article class="card">
        <div class="section-head compact">
            <div>
                <h2>Filtros</h2>
                <p>Visualiza cartera por estado.</p>
            </div>
        </div>
        <div class="pill-group">
            <?php foreach (['todas' => 'Todas', 'pendiente' => 'Pendientes', 'vencida' => 'Vencidas', 'cobrada' => 'Cobradas'] as $value => $label): ?>
                <a href="cartera.php?estado=<?php echo e($value); ?>" class="pill <?php echo $statusFilter === $value ? 'active' : ''; ?>"><?php echo e($label); ?></a>
            <?php endforeach; ?>
        </div>
    </article>
</section>

<section class="section-block">
    <div class="obligation-list">
        <?php if ($items): ?>
            <?php foreach ($items as $item): ?>
                <?php
                $percentage = $item['monto_total'] > 0 ? min(100, max(0, ($item['monto_cobrado'] / $item['monto_total']) * 100)) : 0;
                $days = obligation_days_remaining($item['fecha_limite']);
                ?>
                <article class="card obligation-card is-open">
                    <div class="obligation-topline">
                        <div>
                            <h3><?php echo e($item['persona']); ?></h3>
                            <p><?php echo e($item['concepto']); ?></p>
                        </div>
                        <span class="badge <?php echo e(cartera_status_class($item['estado'])); ?>"><?php echo e(cartera_status_label($item['estado'])); ?></span>
                    </div>
                    <div class="progress-bar-track">
                        <div class="progress-bar-fill" style="width: <?php echo e((string) round($percentage, 2)); ?>%; background: <?php echo e(obligation_progress_color($percentage)); ?>"></div>
                    </div>
                    <div class="obligation-detail-grid">
                        <div>
                            <p><strong>Prestado:</strong> <?php echo e(format_currency((float) $item['monto_total'])); ?></p>
                            <p><strong>Cobrado:</strong> <?php echo e(format_currency((float) $item['monto_cobrado'])); ?></p>
                            <p><strong>Pendiente:</strong> <span class="text-warning"><?php echo e(format_currency((float) $item['saldo_pendiente'])); ?></span></p>
                            <p><strong>Fecha préstamo:</strong> <?php echo e(date('d/m/Y', strtotime($item['fecha_prestamo']))); ?></p>
                            <p><strong>Fecha límite:</strong> <?php echo e($item['fecha_limite'] ? date('d/m/Y', strtotime($item['fecha_limite'])) : 'Sin fecha'); ?></p>
                            <?php if ($days !== null): ?>
                                <p><strong>Seguimiento:</strong> <?php echo e($days >= 0 ? $days . ' días restantes' : abs($days) . ' días de atraso'); ?></p>
                            <?php endif; ?>
                            <p><strong>Notas:</strong> <?php echo e($item['notas'] ?: 'Sin notas'); ?></p>
                        </div>
                        <div class="card-actions wrap">
                            <a class="btn btn-secondary btn-sm" href="cartera.php?edit=<?php echo e((string) $item['id']); ?>">✏️ Editar</a>
                            <form method="post" onsubmit="return confirm('¿Deseas eliminar este registro de cartera?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo e((string) $item['id']); ?>">
                                <button class="btn btn-secondary btn-sm" type="submit">🗑️ Eliminar</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card empty-state">
                <p>No hay cartera registrada en este filtro.</p>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
