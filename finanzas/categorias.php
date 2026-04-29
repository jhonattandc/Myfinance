<?php
require_once __DIR__ . '/config/db.php';
require_login();

$userId = current_user_id();
$pageTitle = 'Categorías';
$activePage = 'categorias.php';
$editCategory = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM gastos WHERE categoria_id = ? AND user_id = ?');
        $countStmt->execute([$id, $userId]);

        if ((int) $countStmt->fetchColumn() > 0) {
            flash('error', 'No puedes eliminar una categoría con gastos asociados.');
        } else {
            $stmt = $pdo->prepare('DELETE FROM categorias WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $userId]);
            flash('success', 'Categoría eliminada correctamente.');
        }

        redirect('categorias.php');
    }

    $data = [
        'nombre' => trim($_POST['nombre'] ?? ''),
        'color' => $_POST['color'] ?? '#6c63ff',
        'icono' => trim($_POST['icono'] ?? '🏷️'),
    ];
    $id = (int) ($_POST['id'] ?? 0);

    if ($data['nombre'] === '') {
        flash('error', 'El nombre de la categoría es obligatorio.');
    } else {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE categorias SET nombre = ?, color = ?, icono = ? WHERE id = ? AND user_id = ?');
            $stmt->execute([$data['nombre'], $data['color'], $data['icono'], $id, $userId]);
            flash('success', 'Categoría actualizada.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO categorias (nombre, color, icono, user_id) VALUES (?, ?, ?, ?)');
            $stmt->execute([$data['nombre'], $data['color'], $data['icono'], $userId]);
            flash('success', 'Categoría creada correctamente.');
        }
    }

    redirect('categorias.php');
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM categorias WHERE id = ? AND user_id = ?');
    $stmt->execute([(int) $_GET['edit'], $userId]);
    $editCategory = $stmt->fetch();
}

$stmt = $pdo->prepare("SELECT c.*, COUNT(g.id) AS total_gastos FROM categorias c LEFT JOIN gastos g ON g.categoria_id = c.id AND g.user_id = c.user_id WHERE c.user_id = ? GROUP BY c.id ORDER BY c.nombre");
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<section class="content-grid split-main">
    <article class="card">
        <div class="section-head compact">
            <div>
                <h2><?php echo $editCategory ? 'Editar categoría' : 'Nueva categoría'; ?></h2>
                <p>Crea etiquetas visuales para clasificar tus gastos.</p>
            </div>
        </div>
        <form method="post" class="stack-form">
            <input type="hidden" name="id" value="<?php echo e((string) ($editCategory['id'] ?? '')); ?>">
            <label>
                <span>Nombre</span>
                <input type="text" name="nombre" required value="<?php echo e($editCategory['nombre'] ?? ''); ?>">
            </label>
            <div class="form-row two-columns">
                <label>
                    <span>Color</span>
                    <input type="color" name="color" value="<?php echo e($editCategory['color'] ?? '#6c63ff'); ?>">
                </label>
                <label>
                    <span>Ícono</span>
                    <input type="text" name="icono" value="<?php echo e($editCategory['icono'] ?? '🏷️'); ?>">
                </label>
            </div>
            <button class="btn btn-primary" type="submit"><?php echo $editCategory ? 'Guardar cambios' : 'Crear categoría'; ?></button>
        </form>
    </article>

    <article class="card table-card table-scroll">
        <div class="section-head compact">
            <div>
                <h2>Categorías</h2>
                <p>Listado completo y uso asociado.</p>
            </div>
        </div>
        <?php if ($categories): ?>
            <table>
                <thead>
                    <tr>
                        <th>Preview</th>
                        <th>Ícono</th>
                        <th>Nombre</th>
                        <th>N° gastos</th>
                        <th>Editar</th>
                        <th>Eliminar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><span class="color-dot" style="background: <?php echo e($category['color']); ?>"></span></td>
                            <td><?php echo e($category['icono']); ?></td>
                            <td><?php echo e($category['nombre']); ?></td>
                            <td><?php echo e((string) $category['total_gastos']); ?></td>
                            <td><a class="icon-button text-link" href="categorias.php?edit=<?php echo e((string) $category['id']); ?>">✏️</a></td>
                            <td>
                                <form method="post" onsubmit="return confirm('¿Deseas eliminar esta categoría?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo e((string) $category['id']); ?>">
                                    <button class="icon-button danger-link" type="submit" <?php echo $category['total_gastos'] > 0 ? 'disabled' : ''; ?>>🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state padded-empty">
                <p>Aún no has creado categorías.</p>
                <p class="text-secondary">Empieza con categorías como Alimentación, Transporte o Servicios para poder registrar gastos correctamente.</p>
            </div>
        <?php endif; ?>
    </article>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
