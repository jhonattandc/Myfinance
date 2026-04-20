<?php
require_once __DIR__ . '/config/db.php';
require_login();

$userId = current_user_id();
$pageTitle = 'Obligaciones';
$activePage = 'obligaciones.php';
$editObligation = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM obligaciones WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        flash('success', 'Obligación eliminada correctamente.');
        redirect('obligaciones.php');
    }

    if ($action === 'quick_payment') {
        $obligationId = (int) ($_POST['obligacion_id'] ?? 0);
        if (!validate_obligation_ownership($pdo, $obligationId, $userId, true)) {
            flash('error', 'No puedes registrar pagos sobre esa obligación.');
            redirect('obligaciones.php');
        }

        $defaultCategoryStmt = $pdo->prepare('SELECT id FROM categorias WHERE user_id = ? ORDER BY id LIMIT 1');
        $defaultCategoryStmt->execute([$userId]);
        $defaultCategory = (int) $defaultCategoryStmt->fetchColumn();

        $data = [
            'descripcion' => trim($_POST['descripcion_pago'] ?? ''),
            'monto' => (float) ($_POST['monto_pago'] ?? 0),
            'fecha' => $_POST['fecha_pago'] ?? date('Y-m-d'),
            'categoria_id' => $defaultCategory,
            'obligacion_id' => $obligationId,
            'es_pago_obligacion' => 1,
            'notas' => trim($_POST['notas_pago'] ?? ''),
        ];

        if ($data['descripcion'] === '' || $data['monto'] <= 0 || $defaultCategory <= 0) {
            flash('error', 'Completa la información del pago rápido y verifica que exista al menos una categoría.');
        } else {
            upsert_expense($pdo, $userId, $data, null);
            flash('success', 'Pago registrado y saldo actualizado.');
        }

        redirect('obligaciones.php');
    }

    $data = [
        'nombre' => trim($_POST['nombre'] ?? ''),
        'descripcion' => trim($_POST['descripcion'] ?? ''),
        'monto_total' => (float) ($_POST['monto_total'] ?? 0),
        'fecha_inicio' => $_POST['fecha_inicio'] ?? date('Y-m-d'),
        'fecha_limite' => $_POST['fecha_limite'] ?: null,
        'icono' => trim($_POST['icono'] ?? '🏦'),
        'color' => $_POST['color'] ?? '#63b3ed',
        'estado' => $_POST['estado'] ?? 'activa',
    ];
    $id = (int) ($_POST['id'] ?? 0);

    if ($data['nombre'] === '' || $data['monto_total'] <= 0 || $data['fecha_inicio'] === '') {
        flash('error', 'Nombre, monto total y fecha inicio son obligatorios.');
    } else {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE obligaciones SET nombre = ?, descripcion = ?, monto_total = ?, fecha_inicio = ?, fecha_limite = ?, icono = ?, color = ?, estado = ? WHERE id = ? AND user_id = ?');
            $stmt->execute([$data['nombre'], $data['descripcion'], $data['monto_total'], $data['fecha_inicio'], $data['fecha_limite'], $data['icono'], $data['color'], $data['estado'], $id, $userId]);
            flash('success', 'Obligación actualizada.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO obligaciones (nombre, descripcion, monto_total, fecha_inicio, fecha_limite, icono, color, estado, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$data['nombre'], $data['descripcion'], $data['monto_total'], $data['fecha_inicio'], $data['fecha_limite'], $data['icono'], $data['color'], $data['estado'], $userId]);
            flash('success', 'Obligación creada correctamente.');
        }
    }

    redirect('obligaciones.php');
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM obligaciones WHERE id = ? AND user_id = ?');
    $stmt->execute([(int) $_GET['edit'], $userId]);
    $editObligation = $stmt->fetch();
}

$statusFilter = $_GET['estado'] ?? 'todas';
$sql = "SELECT *, (monto_total - monto_pagado) AS saldo_pendiente FROM obligaciones WHERE user_id = :user_id";
$params = ['user_id' => $userId];

if (in_array($statusFilter, ['activa', 'pagada', 'congelada'], true)) {
    $sql .= ' AND estado = :estado';
    $params['estado'] = $statusFilter;
}

$sql .= " ORDER BY FIELD(estado, 'activa', 'congelada', 'pagada'), fecha_limite IS NULL, fecha_limite ASC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$obligations = $stmt->fetchAll();

$summaryStmt = $pdo->prepare('SELECT COALESCE(SUM(monto_total), 0) AS total_original, COALESCE(SUM(monto_pagado), 0) AS total_pagado, COALESCE(SUM(monto_total - monto_pagado), 0) AS total_pendiente FROM obligaciones WHERE user_id = ?');
$summaryStmt->execute([$userId]);
$summary = $summaryStmt->fetch();

$paymentsByObligation = [];
if ($obligations) {
    $placeholders = implode(',', array_fill(0, count($obligations), '?'));
    $ids = array_map(static fn($item) => (int) $item['id'], $obligations);
    $paymentSql = "SELECT id, obligacion_id, fecha, descripcion, monto FROM gastos WHERE user_id = ? AND obligacion_id IN ({$placeholders}) ORDER BY fecha DESC, id DESC";
    $paymentStmt = $pdo->prepare($paymentSql);
    $paymentStmt->execute(array_merge([$userId], $ids));

    foreach ($paymentStmt->fetchAll() as $payment) {
        $paymentsByObligation[$payment['obligacion_id']][] = $payment;
    }
}

include __DIR__ . '/includes/header.php';
?>
<section class="grid-kpis three-up">
    <article class="kpi-card card">
        <span>Total deuda original</span>
        <strong><?php echo e(format_currency((float) $summary['total_original'])); ?></strong>
    </article>
    <article class="kpi-card card">
        <span>Total pagado</span>
        <strong class="text-success"><?php echo e(format_currency((float) $summary['total_pagado'])); ?></strong>
    </article>
    <article class="kpi-card card">
        <span>Total pendiente</span>
        <strong class="text-danger"><?php echo e(format_currency((float) $summary['total_pendiente'])); ?></strong>
    </article>
</section>

<section class="content-grid split-main obligations-layout">
    <article class="card">
        <div class="section-head compact">
            <div>
                <h2><?php echo $editObligation ? 'Editar obligación' : 'Nueva obligación'; ?></h2>
                <p>Registra créditos, arriendos o cuotas pendientes.</p>
            </div>
        </div>
        <form method="post" class="stack-form">
            <input type="hidden" name="id" value="<?php echo e((string) ($editObligation['id'] ?? '')); ?>">
            <label>
                <span>Nombre de la obligación</span>
                <input type="text" name="nombre" required value="<?php echo e($editObligation['nombre'] ?? ''); ?>">
            </label>
            <label>
                <span>Descripción</span>
                <textarea name="descripcion" rows="3"><?php echo e($editObligation['descripcion'] ?? ''); ?></textarea>
            </label>
            <label>
                <span>Monto total de la deuda COP</span>
                <input type="number" step="0.01" min="0" name="monto_total" required value="<?php echo e((string) ($editObligation['monto_total'] ?? '')); ?>">
            </label>
            <div class="form-row two-columns">
                <label>
                    <span>Fecha inicio</span>
                    <input type="date" name="fecha_inicio" required value="<?php echo e($editObligation['fecha_inicio'] ?? date('Y-m-d')); ?>">
                </label>
                <label>
                    <span>Fecha límite</span>
                    <input type="date" name="fecha_limite" value="<?php echo e($editObligation['fecha_limite'] ?? ''); ?>">
                </label>
            </div>
            <div class="form-row two-columns">
                <label>
                    <span>Ícono emoji</span>
                    <input type="text" name="icono" value="<?php echo e($editObligation['icono'] ?? '🏦'); ?>">
                </label>
                <label>
                    <span>Color</span>
                    <input type="color" name="color" value="<?php echo e($editObligation['color'] ?? '#63b3ed'); ?>">
                </label>
            </div>
            <label>
                <span>Estado</span>
                <select name="estado">
                    <option value="activa" <?php echo (($editObligation['estado'] ?? 'activa') === 'activa') ? 'selected' : ''; ?>>Activa</option>
                    <option value="congelada" <?php echo (($editObligation['estado'] ?? '') === 'congelada') ? 'selected' : ''; ?>>Congelada</option>
                    <option value="pagada" <?php echo (($editObligation['estado'] ?? '') === 'pagada') ? 'selected' : ''; ?>>Pagada</option>
                </select>
            </label>
            <button class="btn btn-primary" type="submit"><?php echo $editObligation ? 'Guardar cambios' : 'Crear obligación'; ?></button>
        </form>
    </article>

    <article class="card">
        <div class="section-head compact">
            <div>
                <h2>Filtros</h2>
                <p>Alterna entre estados.</p>
            </div>
        </div>
        <div class="pill-group">
            <?php foreach (['todas' => 'Todas', 'activa' => 'Activas', 'pagada' => 'Pagadas', 'congelada' => 'Congeladas'] as $value => $label): ?>
                <a href="obligaciones.php?estado=<?php echo e($value); ?>" class="pill <?php echo $statusFilter === $value ? 'active' : ''; ?>"><?php echo e($label); ?></a>
            <?php endforeach; ?>
        </div>
    </article>
</section>

<section class="section-block">
    <div class="obligation-list">
        <?php foreach ($obligations as $obligation): ?>
            <?php
            $percentage = $obligation['monto_total'] > 0 ? min(100, max(0, ($obligation['monto_pagado'] / $obligation['monto_total']) * 100)) : 0;
            $days = obligation_days_remaining($obligation['fecha_limite']);
            $payments = $paymentsByObligation[$obligation['id']] ?? [];
            $paymentsTotal = array_reduce($payments, static fn($carry, $item) => $carry + (float) $item['monto'], 0.0);
            ?>
            <article class="card obligation-card" data-expandable>
                <button class="obligation-summary" type="button" data-expand-toggle>
                    <div>
                        <div class="obligation-topline">
                            <h3><?php echo e($obligation['icono'] . ' ' . $obligation['nombre']); ?></h3>
                            <div class="summary-right">
                                <span class="badge <?php echo e(obligation_status_class($obligation['estado'])); ?>"><?php echo e(obligation_status_label($obligation['estado'])); ?></span>
                                <strong class="text-danger"><?php echo e(format_currency((float) $obligation['saldo_pendiente'])); ?></strong>
                            </div>
                        </div>
                        <div class="progress-bar-track">
                            <div class="progress-bar-fill" style="width: <?php echo e((string) round($percentage, 2)); ?>%; background: <?php echo e(obligation_progress_color($percentage)); ?>"></div>
                        </div>
                        <div class="obligation-meta">
                            <span><?php echo e((string) round($percentage)); ?>% pagado</span>
                            <?php if ($days !== null): ?>
                                <span class="badge <?php echo $days < 7 ? 'danger' : 'neutral'; ?>"><?php echo $days >= 0 ? e($days . ' días restantes') : e(abs($days) . ' días vencida'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </button>
                <div class="expandable-body">
                    <div class="obligation-detail-grid">
                        <div>
                            <p><strong>Monto total:</strong> <?php echo e(format_currency((float) $obligation['monto_total'])); ?></p>
                            <p><strong>Monto pagado:</strong> <?php echo e(format_currency((float) $obligation['monto_pagado'])); ?></p>
                            <p><strong>Saldo pendiente:</strong> <span class="text-danger"><?php echo e(format_currency((float) $obligation['saldo_pendiente'])); ?></span></p>
                            <p><strong>Descripción:</strong> <?php echo e($obligation['descripcion'] ?: 'Sin descripción'); ?></p>
                        </div>
                        <div class="card-actions wrap">
                            <a class="btn btn-secondary btn-sm" href="obligaciones.php?edit=<?php echo e((string) $obligation['id']); ?>">✏️ Editar</a>
                            <form method="post" onsubmit="return confirm('¿Deseas eliminar esta obligación?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo e((string) $obligation['id']); ?>">
                                <button class="btn btn-secondary btn-sm" type="submit">🗑️ Eliminar</button>
                            </form>
                            <button class="btn btn-primary btn-sm" type="button" data-modal-open="modal-<?php echo e((string) $obligation['id']); ?>">Pago rápido</button>
                        </div>
                    </div>
                    <div class="table-scroll inner-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Descripción</th>
                                    <th>Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($payments): ?>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td><?php echo e(date('d/m/Y', strtotime($payment['fecha']))); ?></td>
                                            <td><?php echo e($payment['descripcion']); ?></td>
                                            <td><?php echo e(format_currency((float) $payment['monto'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr>
                                        <td colspan="2"><strong>Total pagado</strong></td>
                                        <td><strong><?php echo e(format_currency($paymentsTotal)); ?></strong></td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3">Aún no hay pagos registrados para esta obligación.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>

            <div class="modal" id="modal-<?php echo e((string) $obligation['id']); ?>">
                <div class="modal-dialog card">
                    <div class="section-head compact">
                        <div>
                            <h2>Registrar pago para <?php echo e($obligation['nombre']); ?></h2>
                            <p>Se registrará como gasto vinculado a esta obligación.</p>
                        </div>
                        <button class="icon-button" type="button" data-modal-close>✕</button>
                    </div>
                    <form method="post" class="stack-form">
                        <input type="hidden" name="action" value="quick_payment">
                        <input type="hidden" name="obligacion_id" value="<?php echo e((string) $obligation['id']); ?>">
                        <label>
                            <span>Descripción del pago</span>
                            <input type="text" name="descripcion_pago" required value="Pago a <?php echo e($obligation['nombre']); ?>">
                        </label>
                        <div class="form-row two-columns">
                            <label>
                                <span>Monto COP</span>
                                <input type="number" step="0.01" min="0" name="monto_pago" required>
                            </label>
                            <label>
                                <span>Fecha</span>
                                <input type="date" name="fecha_pago" required value="<?php echo e(date('Y-m-d')); ?>">
                            </label>
                        </div>
                        <label>
                            <span>Notas</span>
                            <textarea name="notas_pago" rows="3"></textarea>
                        </label>
                        <button class="btn btn-primary" type="submit">Guardar pago</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
