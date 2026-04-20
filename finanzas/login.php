<?php
require_once __DIR__ . '/config/db.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT id, nombre, email, password_hash FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'nombre' => $user['nombre'],
            'email' => $user['email'],
        ];

        flash('success', 'Bienvenido de nuevo, ' . $user['nombre'] . '.');
        redirect('dashboard.php');
    }

    $error = 'Credenciales inválidas. Verifica tu email y contraseña.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión | Finanzas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-card card">
            <div class="login-brand">💸 Finanzas</div>
            <h1>Controla todo tu dinero en un solo lugar</h1>
            <p class="login-copy">Ingresa con tu cuenta demo y empieza a monitorear ingresos, gastos y obligaciones.</p>
            <form method="post" class="stack-form">
                <label>
                    <span>Email</span>
                    <input type="email" name="email" placeholder="wandu@demo.com" required>
                </label>
                <label>
                    <span>Contraseña</span>
                    <input type="password" name="password" placeholder="••••••••" required>
                </label>
                <button type="submit" class="btn btn-primary btn-block">Iniciar sesión</button>
            </form>
            <?php if ($error): ?>
                <div class="inline-toast error show"><?php echo e($error); ?></div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
