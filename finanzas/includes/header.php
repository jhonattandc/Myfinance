<?php
if (!isset($pageTitle)) {
    $pageTitle = 'Finanzas';
}

if (!isset($activePage)) {
    $activePage = '';
}

$flash = get_flash();
$styleVersion = file_exists(__DIR__ . '/../assets/style.css') ? (string) filemtime(__DIR__ . '/../assets/style.css') : '1';
$navigation = [
    'dashboard.php' => '🏠 Dashboard',
    'ingresos.php' => '💰 Ingresos',
    'gastos.php' => '💸 Gastos',
    'cartera.php' => '🧾 Cartera',
    'obligaciones.php' => '🏦 Obligaciones',
    'categorias.php' => '🏷️ Categorías',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> | Finanzas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css?v=<?php echo e($styleVersion); ?>">
    <script>
        window.APP_FLASH = <?php echo json_encode($flash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="brand">💸 Finanzas</div>
            <button class="icon-button sidebar-close" type="button" data-sidebar-close>✕</button>
        </div>
        <nav class="sidebar-nav">
            <?php foreach ($navigation as $file => $label): ?>
                <a href="<?php echo e($file); ?>" class="nav-link <?php echo $activePage === $file ? 'active' : ''; ?>"><?php echo e($label); ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="user-chip">
                <div class="avatar"><?php echo e(current_user_initials()); ?></div>
                <div>
                    <strong><?php echo e(current_user_name()); ?></strong>
                    <span><?php echo e($_SESSION['user']['email'] ?? ''); ?></span>
                </div>
            </div>
            <a href="logout.php" class="btn btn-secondary btn-block">Cerrar sesión</a>
        </div>
    </aside>
    <div class="sidebar-overlay" data-sidebar-close></div>
    <div class="main-column">
        <header class="topbar">
            <button class="icon-button mobile-only" type="button" data-sidebar-open>☰</button>
            <div>
                <h1><?php echo e($pageTitle); ?></h1>
                <p class="topbar-subtitle">Organiza ingresos, gastos y obligaciones sin salir de localhost.</p>
            </div>
        </header>
        <main class="content">
            <div id="flash-root"></div>
