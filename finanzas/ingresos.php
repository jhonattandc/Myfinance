<?php
require_once __DIR__ . '/config/db.php';
require_login();

$userId = current_user_id();
$pageTitle = 'Ingresos';
$activePage = 'ingresos.php';
$activeCartera = fetch_active_cartera($pdo, $userId);
$editIncome = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $deletedIncome = delete_income($pdo, $id, $userId);
        $message = 'Ingreso eliminado correctamente.';

        if ($deletedIncome && !empty($deletedIncome['cartera_id'])) {
            $cartera = fetch_cartera_by_id($pdo, (int) $deletedIncome['cartera_id'], $userId);
            $persona = $cartera['persona'] ?? 'la cartera vinculada';
            $message = 'Ingreso eliminado. Se ajustó automáticamente la cartera de ' . $persona . '.';
        }

        flash('success', $message);
        redirect('ingresos.php');
    }

    $isCarteraIncome = (int) ($_POST['es_cobro_cartera'] ?? 0) === 1;
    $carteraId = $isCarteraIncome ? (int) ($_POST['cartera_id'] ?? 0) : null;

    $data = [
        'descripcion' => trim($_POST['descripcion'] ?? ''),
        'monto' => (float) ($_POST['monto'] ?? 0),
        'fecha' => $_POST['fecha'] ?? date('Y-m-d'),
        'notas' => trim($_POST['notas'] ?? ''),
        'cartera_id' => $carteraId ?: null,
    ];
    $id = (int) ($_POST['id'] ?? 0);

    $errors = [];
    if ($data['descripcion'] === '' || $data['monto'] <= 0 || $data['fecha'] === '') {
        $errors[] = 'Completa descripción, monto y fecha para guardar el ingreso.';
    }
    if ($isCarteraIncome && (!$data['cartera_id'] || !validate_cartera_ownership($pdo, (int) $data['cartera_id'], $userId, true))) {
        $errors[] = 'La cartera seleccionada no está disponible para este usuario.';
    }
    if ($data['cartera_id']) {
        $selectedCartera = fetch_cartera_by_id($pdo, (int) $data['cartera_id'], $userId);
        if (!$selectedCartera) {
            $errors[] = 'No se encontró la cartera seleccionada.';
        } else {
            $currentIncome = $id ? fetch_income_by_id($pdo, $id, $userId) : null;
            $availablePending = (float) $selectedCartera['saldo_pendiente'];

            if ($currentIncome && (int) ($currentIncome['cartera_id'] ?? 0) === (int) $data['cartera_id']) {
                $availablePending += (float) $currentIncome['monto'];
            }

            if ($data['monto'] > $availablePending) {
                $errors[] = 'El monto del ingreso supera el saldo pendiente de la cartera seleccionada.';
            }
        }
    }

    if ($errors) {
        flash('error', implode(' ', $errors));
    } else {
        upsert_income($pdo, $userId, $data, $id ?: null);
        flash('success', $data['cartera_id']
            ? ($id ? 'Ingreso actualizado y cartera normalizada.' : 'Ingreso registrado y cartera normalizada.')
            : ($id ? 'Ingreso actualizado.' : 'Ingreso registrado exitosamente.'));
    }

    redirect('ingresos.php');
}

if (isset($_GET['edit'])) {
    $editIncome = fetch_income_by_id($pdo, (int) $_GET['edit'], $userId);
    if ($editIncome && !empty($editIncome['cartera_id'])) {
        $linkedCartera = fetch_cartera_by_id($pdo, (int) $editIncome['cartera_id'], $userId);
        if ($linkedCartera) {
            $alreadyIncluded = array_filter($activeCartera, static fn(array $item): bool => (int) $item['id'] === (int) $linkedCartera['id']);
            if (!$alreadyIncluded) {
                $activeCartera[] = $linkedCartera;
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM ingresos WHERE user_id = ? AND DATE_FORMAT(fecha, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
$stmt->execute([$userId]);
$monthTotal = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM ingresos WHERE user_id = ?");
$stmt->execute([$userId]);
$historicTotal = (float) $stmt->fetchColumn();

$listStmt = $pdo->prepare('SELECT i.*, c.persona AS cartera_persona, c.concepto AS cartera_concepto FROM ingresos i LEFT JOIN cartera c ON c.id = i.cartera_id WHERE i.user_id = ? ORDER BY i.fecha DESC, i.id DESC');
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
                    <input type="number" step="0.01" min="0" name="monto" id="incomeAmount" required value="<?php echo e((string) ($editIncome['monto'] ?? '')); ?>">
                </label>
                <label>
                    <span>Fecha</span>
                    <input type="date" name="fecha" required value="<?php echo e($editIncome['fecha'] ?? date('Y-m-d')); ?>">
                </label>
            </div>
            <?php $checkedCartera = (int) ($editIncome['cartera_id'] ?? 0) > 0; ?>
            <fieldset class="radio-group">
                <legend>¿Este ingreso corresponde a una cartera pendiente?</legend>
                <label><input type="radio" name="es_cobro_cartera" value="0" <?php echo !$checkedCartera ? 'checked' : ''; ?>> No</label>
                <label><input type="radio" name="es_cobro_cartera" value="1" <?php echo $checkedCartera ? 'checked' : ''; ?>> Sí</label>
            </fieldset>
            <div class="conditional-panel <?php echo $checkedCartera ? 'is-visible' : ''; ?>" id="incomeCarteraPanel">
                <label>
                    <span>Selecciona la cartera</span>
                    <select name="cartera_id" id="incomeCarteraSelect">
                        <option value="">Selecciona una cartera</option>
                        <?php foreach ($activeCartera as $cartera): ?>
                            <option
                                value="<?php echo e((string) $cartera['id']); ?>"
                                data-person="<?php echo e($cartera['persona']); ?>"
                                data-concept="<?php echo e($cartera['concepto']); ?>"
                                data-pending="<?php echo e((string) $cartera['saldo_pendiente']); ?>"
                                <?php echo ((int) ($editIncome['cartera_id'] ?? 0) === (int) $cartera['id']) ? 'selected' : ''; ?>
                            >
                                <?php echo e($cartera['persona'] . ' - ' . $cartera['concepto']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="obligation-preview" id="incomeCarteraPreview"></div>
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
                    <th>Cartera</th>
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
                        <td>
                            <?php if ($income['cartera_persona']): ?>
                                <span class="badge obligation"><?php echo e($income['cartera_persona'] . ' - ' . $income['cartera_concepto']); ?></span>
                            <?php else: ?>
                                <span class="text-secondary">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-success"><?php echo e(format_currency((float) $income['monto'])); ?></td>
                        <td><?php echo e($income['notas'] ?: '—'); ?></td>
                        <td><a class="icon-button text-link" href="ingresos.php?edit=<?php echo e((string) $income['id']); ?>">✏️</a></td>
                        <td>
                            <?php $confirm = $income['cartera_persona'] ? 'Este ingreso está vinculado a la cartera de ' . $income['cartera_persona'] . '. Eliminarlo volverá a aumentar el saldo pendiente. ¿Deseas continuar?' : '¿Deseas eliminar este ingreso?'; ?>
                            <form method="post" onsubmit="return confirm('<?php echo e($confirm); ?>');">
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
