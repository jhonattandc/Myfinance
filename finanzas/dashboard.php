<?php
require_once __DIR__ . '/config/db.php';
require_login();

$userId = current_user_id();
$pageTitle = 'Dashboard';
$activePage = 'dashboard.php';

$stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM ingresos WHERE user_id = ? AND DATE_FORMAT(fecha, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
$stmt->execute([$userId]);
$incomeMonth = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM gastos WHERE user_id = ? AND DATE_FORMAT(fecha, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
$stmt->execute([$userId]);
$expenseMonth = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM gastos WHERE user_id = ?");
$stmt->execute([$userId]);
$totalExpenses = (float) $stmt->fetchColumn();

$balanceMonth = $incomeMonth - $expenseMonth;

$obligationStmt = $pdo->prepare("SELECT *, (monto_total - monto_pagado) AS saldo_pendiente FROM obligaciones WHERE user_id = ? AND estado = 'activa' ORDER BY fecha_limite IS NULL, fecha_limite ASC, created_at DESC");
$obligationStmt->execute([$userId]);
$obligations = $obligationStmt->fetchAll();

$recentStmt = $pdo->prepare("SELECT g.*, c.nombre AS categoria_nombre, c.color AS categoria_color, o.nombre AS obligacion_nombre FROM gastos g INNER JOIN categorias c ON c.id = g.categoria_id LEFT JOIN obligaciones o ON o.id = g.obligacion_id WHERE g.user_id = ? ORDER BY g.fecha DESC, g.id DESC LIMIT 10");
$recentStmt->execute([$userId]);
$recentExpenses = $recentStmt->fetchAll();

$chartData = fetch_dashboard_chart_data($pdo, $userId);

include __DIR__ . '/includes/header.php';
?>
<section class="grid-kpis four-up">
    <article class="kpi-card card">
        <span>💰 Ingresos del mes</span>
        <strong class="text-success"><?php echo e(format_currency($incomeMonth)); ?></strong>
    </article>
    <article class="kpi-card card">
        <span>💸 Gastos del mes</span>
        <strong class="text-danger"><?php echo e(format_currency($expenseMonth)); ?></strong>
    </article>
    <article class="kpi-card card">
        <span>📊 Balance del mes</span>
        <strong class="<?php echo $balanceMonth >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo e(format_currency($balanceMonth)); ?></strong>
    </article>
    <article class="kpi-card card">
        <span>📋 Total gastos histórico</span>
        <strong class="text-muted-light"><?php echo e(format_currency($totalExpenses)); ?></strong>
    </article>
</section>

<section class="section-block">
    <div class="section-head">
        <div>
            <h2>🏦 Mis Obligaciones</h2>
            <p>Monitorea el avance de tus pagos activos.</p>
        </div>
        <a href="obligaciones.php" class="btn btn-secondary">Administrar</a>
    </div>
    <?php if ($obligations): ?>
        <div class="obligation-grid dashboard-obligations">
            <?php foreach ($obligations as $obligation): ?>
                <?php
                $percentage = $obligation['monto_total'] > 0 ? min(100, max(0, ($obligation['monto_pagado'] / $obligation['monto_total']) * 100)) : 0;
                $days = obligation_days_remaining($obligation['fecha_limite']);
                ?>
                <article class="card obligation-widget">
                    <div class="obligation-topline">
                        <div>
                            <h3><?php echo e($obligation['icono'] . ' ' . $obligation['nombre']); ?></h3>
                            <p><?php echo e(format_currency((float) $obligation['monto_pagado'])); ?> / <?php echo e(format_currency((float) $obligation['monto_total'])); ?></p>
                        </div>
                        <span class="badge neutral"><?php echo e((string) round($percentage)); ?>%</span>
                    </div>
                    <div class="progress-bar-track">
                        <div class="progress-bar-fill" style="width: <?php echo e((string) round($percentage, 2)); ?>%; background: <?php echo e(obligation_progress_color($percentage)); ?>"></div>
                    </div>
                    <div class="obligation-meta">
                        <span class="text-danger">Pendiente: <?php echo e(format_currency((float) $obligation['saldo_pendiente'])); ?></span>
                        <?php if ($days !== null): ?>
                            <span class="badge <?php echo $days < 7 ? 'danger' : 'neutral'; ?>"><?php echo $days >= 0 ? e($days . ' días restantes') : e(abs($days) . ' días vencida'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="card-actions">
                        <a class="btn btn-secondary btn-sm" href="gastos.php?obligacion=<?php echo e((string) $obligation['id']); ?>">Ver pagos</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card empty-state">
            <p>No tienes obligaciones activas registradas.</p>
            <a href="obligaciones.php" class="btn btn-primary">Registrar primera obligación</a>
        </div>
    <?php endif; ?>
</section>

<section class="chart-grid two-up">
    <article class="card chart-card">
        <div class="section-head compact">
            <div>
                <h2>Gastos por mes</h2>
                <p>Últimos 6 meses</p>
            </div>
        </div>
        <canvas id="expensesMonthlyChart" data-chart='<?php echo e(json_encode($chartData['monthly'], JSON_UNESCAPED_UNICODE)); ?>'></canvas>
    </article>
    <article class="card chart-card">
        <div class="section-head compact">
            <div>
                <h2>Gastos por categoría</h2>
                <p>Mes actual</p>
            </div>
        </div>
        <canvas id="expensesCategoryChart" data-chart='<?php echo e(json_encode($chartData['categories'], JSON_UNESCAPED_UNICODE)); ?>'></canvas>
    </article>
</section>

<section class="section-block">
    <div class="section-head">
        <div>
            <h2>Últimos 10 gastos</h2>
            <p>Movimiento más reciente de tus salidas.</p>
        </div>
    </div>
    <div class="card table-card table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Descripción</th>
                    <th>Categoría</th>
                    <th>Obligación</th>
                    <th>Monto</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentExpenses as $expense): ?>
                    <tr>
                        <td><?php echo e(date('d/m/Y', strtotime($expense['fecha']))); ?></td>
                        <td><?php echo e($expense['descripcion']); ?></td>
                        <td>
                            <span class="badge" style="background: <?php echo e(badge_rgba($expense['categoria_color'])); ?>; color: <?php echo e($expense['categoria_color']); ?>">
                                <?php echo e($expense['categoria_nombre']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($expense['obligacion_nombre']): ?>
                                <span class="badge obligation"><?php echo e('🏦 ' . $expense['obligacion_nombre']); ?></span>
                            <?php else: ?>
                                <span class="text-secondary">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-danger"><?php echo e(format_currency((float) $expense['monto'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
