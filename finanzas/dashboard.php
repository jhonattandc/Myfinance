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
$carteraStmt = $pdo->prepare("SELECT COALESCE(SUM(monto_total - monto_cobrado), 0) FROM cartera WHERE user_id = ? AND (estado IN ('pendiente', 'vencida')) AND (monto_total - monto_cobrado) > 0");
$carteraStmt->execute([$userId]);
$carteraPending = (float) $carteraStmt->fetchColumn();
$projectedBalance = $balanceMonth + $carteraPending;

$obligationStmt = $pdo->prepare("SELECT *, (monto_total - monto_pagado) AS saldo_pendiente FROM obligaciones WHERE user_id = ? AND estado = 'activa' ORDER BY fecha_limite IS NULL, fecha_limite ASC, created_at DESC");
$obligationStmt->execute([$userId]);
$obligations = $obligationStmt->fetchAll();

$carteraItems = fetch_active_cartera($pdo, $userId);

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
        <small class="metric-note">Con cartera proyecta: <?php echo e(format_currency($projectedBalance)); ?></small>
    </article>
    <article class="kpi-card card">
        <span>🧾 Cartera pendiente</span>
        <strong class="text-warning"><?php echo e(format_currency($carteraPending)); ?></strong>
        <small class="metric-note">Pendiente por ingresar</small>
    </article>
</section>

<section class="section-block collapsible-section is-open" data-dashboard-section data-storage-key="dashboard-cartera-open">
    <div class="section-head">
        <div>
            <h2>🧾 Cartera por cobrar</h2>
            <p>Dinero prestado que aún no ha entrado a caja.</p>
        </div>
        <div class="section-actions">
            <button class="btn btn-secondary btn-toggle" type="button" data-dashboard-toggle aria-expanded="true">
                <span class="toggle-icon" aria-hidden="true">^</span>
                <span data-toggle-label>Ocultar</span>
            </button>
            <a href="cartera.php" class="btn btn-secondary">Administrar</a>
        </div>
    </div>
    <div class="collapsible-body">
    <?php if ($carteraItems): ?>
        <div class="obligation-grid dashboard-obligations">
            <?php foreach ($carteraItems as $item): ?>
                <?php
                $percentage = $item['monto_total'] > 0 ? min(100, max(0, ($item['monto_cobrado'] / $item['monto_total']) * 100)) : 0;
                $days = obligation_days_remaining($item['fecha_limite']);
                ?>
                <article class="card obligation-widget">
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
                    <div class="obligation-meta">
                        <span>Cobrado: <?php echo e(format_currency((float) $item['monto_cobrado'])); ?> / <?php echo e(format_currency((float) $item['monto_total'])); ?></span>
                        <?php if ($days !== null): ?>
                            <span class="badge <?php echo $days < 0 ? 'danger' : 'neutral'; ?>"><?php echo $days >= 0 ? e($days . ' días restantes') : e(abs($days) . ' días vencida'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="card-actions">
                        <span class="text-warning">Pendiente: <?php echo e(format_currency((float) $item['saldo_pendiente'])); ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card empty-state">
            <p>No tienes cartera registrada.</p>
            <a href="cartera.php" class="btn btn-primary">Registrar cartera</a>
        </div>
    <?php endif; ?>
    </div>
</section>

<section class="section-block collapsible-section is-open" data-dashboard-section data-storage-key="dashboard-obligations-open">
    <div class="section-head">
        <div>
            <h2>🏦 Mis Obligaciones</h2>
            <p>Monitorea el avance de tus pagos activos.</p>
        </div>
        <div class="section-actions">
            <button class="btn btn-secondary btn-toggle" type="button" data-dashboard-toggle aria-expanded="true">
                <span class="toggle-icon" aria-hidden="true">^</span>
                <span data-toggle-label>Ocultar</span>
            </button>
            <a href="obligaciones.php" class="btn btn-secondary">Administrar</a>
        </div>
    </div>
    <div class="collapsible-body">
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
    </div>
</section>

<section class="chart-grid two-up">
    <article class="card chart-card">
        <div class="section-head compact">
            <div>
                <h2>Gastos por mes</h2>
                <p>Últimos 6 meses</p>
            </div>
        </div>
        <div class="chart-frame">
            <canvas id="expensesMonthlyChart" data-chart='<?php echo e(json_encode($chartData['monthly'], JSON_UNESCAPED_UNICODE)); ?>'></canvas>
        </div>
    </article>
    <article class="card chart-card">
        <div class="section-head compact">
            <div>
                <h2>Gastos por categoría</h2>
                <p>Mes actual</p>
            </div>
        </div>
        <div class="chart-frame">
            <canvas id="expensesCategoryChart" data-chart='<?php echo e(json_encode($chartData['categories'], JSON_UNESCAPED_UNICODE)); ?>'></canvas>
        </div>
    </article>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
