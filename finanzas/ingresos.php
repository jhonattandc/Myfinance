<?php
require_once __DIR__ . '/config/db.php';
require_login();

$userId = current_user_id();
$pageTitle = 'Ingresos';
$activePage = 'ingresos.php';
$editIncome = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM ingresos WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);
        flash('success', 'Ingreso eliminado correctamente.');
        redirect('ingresos.php');
    }

    $data = [
        'descripcion' => trim($_POST['descripcion'] ?? ''),
        'monto' => (float) ($_POST['monto'] ?? 0),
        'fecha' => $_POST['fecha'] ?? date('Y-m-d'),
        'notas' => trim($_POST['notas'] ?? ''),
    ];
    $id = (int) ($_POST['id'] ?? 0);

    if ($data['descripcion'] === '' || $data['monto'] <= 0 || $data['fecha'] === '') {
        flash('error', 'Completa descripción, monto y fecha para guardar el ingreso.');
    } else {
        upsert_income($pdo, $userId, $data, $id ?: null);
        flash('success', $id ? 'Ingreso actualizado.' : 'Ingreso registrado exitosamente.');
    }

    redirect('ingresos.php');
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM ingresos WHERE id = ? AND user_id = ?');
    $stmt->execute([(int) $_GET['edit'], $userId]);
    $editIncome = $stmt->fetch();
}

$stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM ingresos WHERE user_id = ? AND DATE_FORMAT(fecha, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
$stmt->execute([$userId]);
$monthTotal = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM ingresos WHERE user_id = ?");
$stmt->execute([$userId]);
$historicTotal = (float) $stmt->fetchColumn();

$listStmt = $pdo->prepare('SELECT * FROM ingresos WHERE user_id = ? ORDER BY fecha DESC, id DESC');
$listStmt->execute([$userId]);
$incomes = $listStmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<section class="grid-kpis two-up">
    <article class="kpi-card card">
        <span>Total del mes</span>
        <strong class="text-success"><?php echo e(format_currency($monthTotal)); ?></strong>
    </article>
    <article class="kpi-card card">
        <span>Total histórico</span>
        <strong><?php echo e(format_currency($historicTotal)); ?></strong>
    </article>
</section>

<section class="content-grid split-main">
    <article class="card">
        <div class="section-head compact">
            <div>
                <h2><?php echo $editIncome ? 'Editar ingreso' : 'Nuevo ingreso'; ?></h2>
                <p>Registra dinero que entra a tu flujo.</p>
            </div>
        </div>
        <form method="post" class="stack-form">
            <input type="hidden" name="id" value="<?php echo e((string) ($editIncome['id'] ?? '')); ?>">
            <label>
                <span>Descripción</span>
                <input type="text" name="descripcion" required value="<?php echo e($editIncome['descripcion'] ?? ''); ?>">
            </label>
            <div class="form-row two-columns">
                <label>
                    <span>Monto COP</span>
                    <input type="number" step="0.01" min="0" name="monto" required value="<?php echo e((string) ($editIncome['monto'] ?? '')); ?>">
                </label>
                <label>
                    <span>Fecha</span>
                    <input type="date" name="fecha" required value="<?php echo e($editIncome['fecha'] ?? date('Y-m-d')); ?>">
                </label>
            </div>
            <label>
                <span>Notas</span>
                <textarea name="notas" rows="4"><?php echo e($editIncome['notas'] ?? ''); ?></textarea>
            </label>
            <button type="submit" class="btn btn-primary"><?php echo $editIncome ? 'Guardar cambios' : 'Registrar ingreso'; ?></button>
        </form>
    </article>

    <article class="card table-card table-scroll">
        <div class="section-head compact">
            <div>
                <h2>Historial</h2>
                <p>Todos tus ingresos registrados.</p>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Descripción</th>
                    <th>Monto</th>
                    <th>Notas</th>
                    <th>Editar</th>
                    <th>Eliminar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($incomes as $income): ?>
                    <tr>
                        <td><?php echo e(date('d/m/Y', strtotime($income['fecha']))); ?></td>
                        <td><?php echo e($income['descripcion']); ?></td>
                        <td class="text-success"><?php echo e(format_currency((float) $income['monto'])); ?></td>
                        <td><?php echo e($income['notas'] ?: '—'); ?></td>
                        <td><a class="icon-button text-link" href="ingresos.php?edit=<?php echo e((string) $income['id']); ?>">✏️</a></td>
                        <td>
                            <form method="post" onsubmit="return confirm('¿Deseas eliminar este ingreso?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo e((string) $income['id']); ?>">
                                <button class="icon-button danger-link" type="submit">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </article>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
