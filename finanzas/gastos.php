<?php
require_once __DIR__ . '/config/db.php';
require_login();

$userId = current_user_id();
$pageTitle = 'Gastos';
$activePage = 'gastos.php';
$categories = fetch_categories($pdo, $userId);
$activeObligations = fetch_active_obligations($pdo, $userId);
$editExpense = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'quick_category') {
        $name = trim($_POST['quick_nombre'] ?? '');
        $color = $_POST['quick_color'] ?? '#6c63ff';
        $icon = trim($_POST['quick_icono'] ?? '🏷️');

        if ($name === '') {
            flash('error', 'Necesitas crear al menos una categoría antes de registrar gastos.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO categorias (nombre, color, icono, user_id) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $color, $icon, $userId]);
            flash('success', 'Categoría creada. Ya puedes registrar tu gasto.');
        }

        redirect('gastos.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT o.nombre FROM gastos g LEFT JOIN obligaciones o ON o.id = g.obligacion_id WHERE g.id = ? AND g.user_id = ?');
        $stmt->execute([$id, $userId]);
        $linked = $stmt->fetchColumn();

        $stmt = $pdo->prepare('DELETE FROM gastos WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $userId]);

        flash('success', $linked ? 'Gasto eliminado. Se ajustó el saldo de la obligación vinculada.' : 'Gasto eliminado correctamente.');
        redirect('gastos.php');
    }

    $isObligationPayment = (int) ($_POST['es_pago_obligacion'] ?? 0) === 1 ? 1 : 0;
    $obligationId = $isObligationPayment ? (int) ($_POST['obligacion_id'] ?? 0) : null;

    $data = [
        'descripcion' => trim($_POST['descripcion'] ?? ''),
        'monto' => (float) ($_POST['monto'] ?? 0),
        'fecha' => $_POST['fecha'] ?? date('Y-m-d'),
        'categoria_id' => (int) ($_POST['categoria_id'] ?? 0),
        'obligacion_id' => $obligationId ?: null,
        'es_pago_obligacion' => $isObligationPayment,
        'notas' => trim($_POST['notas'] ?? ''),
    ];
    $id = (int) ($_POST['id'] ?? 0);

    $errors = [];
    if ($data['descripcion'] === '') {
        $errors[] = 'La descripción es obligatoria.';
    }
    if ($data['monto'] <= 0) {
        $errors[] = 'El monto debe ser mayor a cero.';
    }
    if (!validate_category_ownership($pdo, $data['categoria_id'], $userId)) {
        $errors[] = 'La categoría seleccionada no es válida.';
    }
    if ($isObligationPayment && (!$data['obligacion_id'] || !validate_obligation_ownership($pdo, (int) $data['obligacion_id'], $userId, true))) {
        $errors[] = 'La obligación seleccionada no está disponible para este usuario.';
    }

    if ($errors) {
        flash('error', implode(' ', $errors));
    } else {
        upsert_expense($pdo, $userId, $data, $id ?: null);
        flash('success', $id ? 'Gasto actualizado correctamente.' : 'Gasto registrado correctamente.');
    }

    redirect('gastos.php');
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM gastos WHERE id = ? AND user_id = ?');
    $stmt->execute([(int) $_GET['edit'], $userId]);
    $editExpense = $stmt->fetch();
}

$filters = [
    'categoria' => (int) ($_GET['categoria'] ?? 0),
    'periodo' => trim($_GET['periodo'] ?? ''),
    'tipo' => $_GET['tipo'] ?? 'todos',
    'obligacion' => (int) ($_GET['obligacion'] ?? 0),
];

$sql = "SELECT g.*, c.nombre AS categoria_nombre, c.color AS categoria_color, o.nombre AS obligacion_nombre
        FROM gastos g
        INNER JOIN categorias c ON c.id = g.categoria_id
        LEFT JOIN obligaciones o ON o.id = g.obligacion_id
        WHERE g.user_id = :user_id";
$params = ['user_id' => $userId];

if ($filters['categoria'] > 0) {
    $sql .= ' AND g.categoria_id = :categoria';
    $params['categoria'] = $filters['categoria'];
}
if ($filters['periodo'] !== '') {
    $sql .= " AND DATE_FORMAT(g.fecha, '%Y-%m') = :periodo";
    $params['periodo'] = $filters['periodo'];
}
if ($filters['tipo'] === 'obligaciones') {
    $sql .= ' AND g.es_pago_obligacion = 1';
}
if ($filters['tipo'] === 'comunes') {
    $sql .= ' AND g.es_pago_obligacion = 0';
}
if ($filters['obligacion'] > 0) {
    $sql .= ' AND g.obligacion_id = :obligacion';
    $params['obligacion'] = $filters['obligacion'];
}

$sql .= ' ORDER BY g.fecha DESC, g.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();
$visibleTotal = array_reduce($expenses, static fn($carry, $item) => $carry + (float) $item['monto'], 0.0);

include __DIR__ . '/includes/header.php';
?>
<section class="content-grid split-main">
    <article class="card">
        <div class="section-head compact">
            <div>
                <h2><?php echo $editExpense ? 'Editar gasto' : 'Registrar gasto'; ?></h2>
                <p>Captura gastos comunes o pagos vinculados a obligaciones.</p>
            </div>
        </div>
        <?php if (!$categories): ?>
            <div class="empty-state">
                <p>No puedes registrar gastos todavía porque no tienes categorías creadas.</p>
                <p class="text-secondary">Crea tu primera categoría aquí mismo o administra todas desde el módulo de categorías.</p>
            </div>
            <form method="post" class="stack-form">
                <input type="hidden" name="action" value="quick_category">
                <label>
                    <span>Nombre de la categoría</span>
                    <input type="text" name="quick_nombre" placeholder="Ej: Alimentación" required>
                </label>
                <div class="form-row two-columns">
                    <label>
                        <span>Color</span>
                        <input type="color" name="quick_color" value="#6c63ff">
                    </label>
                    <label>
                        <span>Ícono</span>
                        <input type="text" name="quick_icono" value="🏷️">
                    </label>
                </div>
                <div class="card-actions wrap">
                    <button type="submit" class="btn btn-primary">Crear categoría</button>
                    <a href="categorias.php" class="btn btn-secondary">Gestionar categorías</a>
                </div>
            </form>
        <?php else: ?>
            <form method="post" class="stack-form" id="expenseForm">
                <input type="hidden" name="id" value="<?php echo e((string) ($editExpense['id'] ?? '')); ?>">
                <label>
                    <span>Descripción</span>
                    <input type="text" name="descripcion" required value="<?php echo e($editExpense['descripcion'] ?? ''); ?>">
                </label>
                <div class="form-row two-columns">
                    <label>
                        <span>Monto COP</span>
                        <input type="number" step="0.01" min="0" name="monto" id="expenseAmount" required value="<?php echo e((string) ($editExpense['monto'] ?? '')); ?>">
                    </label>
                    <label>
                        <span>Fecha</span>
                        <input type="date" name="fecha" required value="<?php echo e($editExpense['fecha'] ?? date('Y-m-d')); ?>">
                    </label>
                </div>
                <label>
                    <span>Categoría</span>
                    <select name="categoria_id" required>
                        <option value="">Selecciona</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo e((string) $category['id']); ?>" <?php echo ((int) ($editExpense['categoria_id'] ?? 0) === (int) $category['id']) ? 'selected' : ''; ?>>
                                <?php echo e($category['icono'] . ' ' . $category['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php $checkedObligation = (int) ($editExpense['es_pago_obligacion'] ?? 0) === 1; ?>
                <fieldset class="radio-group">
                    <legend>¿Este gasto corresponde a una obligación?</legend>
                    <label><input type="radio" name="es_pago_obligacion" value="0" <?php echo !$checkedObligation ? 'checked' : ''; ?>> No</label>
                    <label><input type="radio" name="es_pago_obligacion" value="1" <?php echo $checkedObligation ? 'checked' : ''; ?>> Sí</label>
                </fieldset>
                <div class="conditional-panel <?php echo $checkedObligation ? 'is-visible' : ''; ?>" id="obligationPanel">
                    <label>
                        <span>Selecciona una obligación</span>
                        <select name="obligacion_id" id="obligationSelect">
                            <option value="">Selecciona una obligación</option>
                            <?php foreach ($activeObligations as $obligation): ?>
                                <option
                                    value="<?php echo e((string) $obligation['id']); ?>"
                                    data-name="<?php echo e($obligation['nombre']); ?>"
                                    data-icon="<?php echo e($obligation['icono']); ?>"
                                    data-total="<?php echo e((string) $obligation['monto_total']); ?>"
                                    data-paid="<?php echo e((string) $obligation['monto_pagado']); ?>"
                                    data-pending="<?php echo e((string) $obligation['saldo_pendiente']); ?>"
                                    <?php echo ((int) ($editExpense['obligacion_id'] ?? 0) === (int) $obligation['id']) ? 'selected' : ''; ?>
                                >
                                    <?php echo e($obligation['icono'] . ' ' . $obligation['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="obligation-preview" id="obligationPreview"></div>
                </div>
                <label>
                    <span>Notas (opcional)</span>
                    <textarea name="notas" rows="4"><?php echo e($editExpense['notas'] ?? ''); ?></textarea>
                </label>
                <div class="card-actions wrap">
                    <button type="submit" class="btn btn-primary"><?php echo $editExpense ? 'Guardar cambios' : 'Registrar gasto'; ?></button>
                    <a href="categorias.php" class="btn btn-secondary">Gestionar categorías</a>
                </div>
            </form>
        <?php endif; ?>
    </article>

    <article class="card table-card">
        <div class="section-head compact">
            <div>
                <h2>Filtros</h2>
                <p>Refina la tabla de gastos.</p>
            </div>
        </div>
        <form method="get" class="stack-form inline-filter-form">
            <div class="form-row two-columns">
                <label>
                    <span>Por categoría</span>
                    <select name="categoria">
                        <option value="0">Todas</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo e((string) $category['id']); ?>" <?php echo $filters['categoria'] === (int) $category['id'] ? 'selected' : ''; ?>>
                                <?php echo e($category['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Por mes/año</span>
                    <select name="periodo">
                        <option value="">Todos</option>
                        <?php foreach (month_options() as $value => $label): ?>
                            <option value="<?php echo e($value); ?>" <?php echo $filters['periodo'] === $value ? 'selected' : ''; ?>>
                                <?php echo e($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="form-row two-columns">
                <label>
                    <span>Tipo</span>
                    <select name="tipo">
                        <option value="todos" <?php echo $filters['tipo'] === 'todos' ? 'selected' : ''; ?>>Todos</option>
                        <option value="obligaciones" <?php echo $filters['tipo'] === 'obligaciones' ? 'selected' : ''; ?>>Solo obligaciones</option>
                        <option value="comunes" <?php echo $filters['tipo'] === 'comunes' ? 'selected' : ''; ?>>Solo gastos comunes</option>
                    </select>
                </label>
                <label>
                    <span>Obligación</span>
                    <select name="obligacion">
                        <option value="0">Todas</option>
                        <?php foreach ($activeObligations as $obligation): ?>
                            <option value="<?php echo e((string) $obligation['id']); ?>" <?php echo $filters['obligacion'] === (int) $obligation['id'] ? 'selected' : ''; ?>>
                                <?php echo e($obligation['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <button class="btn btn-secondary" type="submit">Aplicar filtros</button>
        </form>
    </article>
</section>

<section class="section-block">
    <div class="section-head">
        <div>
            <h2>Gastos registrados</h2>
            <p>Total visible: <?php echo e(format_currency($visibleTotal)); ?></p>
        </div>
    </div>
    <div class="card table-card table-scroll">
        <?php if ($expenses): ?>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Descripción</th>
                        <th>Categoría</th>
                        <th>Obligación</th>
                        <th>Monto</th>
                        <th>Editar</th>
                        <th>Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $expense): ?>
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
                            <td><a class="icon-button text-link" href="gastos.php?edit=<?php echo e((string) $expense['id']); ?>">✏️</a></td>
                            <td>
                                <?php $confirm = $expense['obligacion_nombre'] ? 'Este gasto está vinculado a la obligación ' . $expense['obligacion_nombre'] . '. Eliminarlo reducirá el monto pagado. ¿Deseas continuar?' : '¿Deseas eliminar este gasto?'; ?>
                                <form method="post" onsubmit="return confirm('<?php echo e($confirm); ?>');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo e((string) $expense['id']); ?>">
                                    <button class="icon-button danger-link" type="submit">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state padded-empty">
                <p>No hay gastos registrados todavía.</p>
                <p class="text-secondary">Cuando registres el primero, aparecerá aquí con sus filtros y estado.</p>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
