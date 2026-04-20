<?php
session_start();

$host = '127.0.0.1';
$db = 'finanzas';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    $message = $e->getMessage();
    if ((int) $e->getCode() === 1049 || str_contains($message, 'Unknown database')) {
        http_response_code(500);
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Base de datos no inicializada</title>
            <style>
                body { margin: 0; font-family: Inter, Arial, sans-serif; background: #0f1117; color: #e2e8f0; display: grid; place-items: center; min-height: 100vh; padding: 24px; }
                .card { max-width: 760px; background: #1a1d27; border: 1px solid #2a2d3e; border-radius: 16px; padding: 28px; box-shadow: 0 12px 30px rgba(0,0,0,.28); }
                h1 { margin-top: 0; font-size: 22px; }
                p, li { color: #8892a4; line-height: 1.6; }
                code { background: #1e2130; padding: 2px 6px; border-radius: 6px; color: #e2e8f0; }
            </style>
        </head>
        <body>
            <div class="card">
                <h1>La base de datos <code>finanzas</code> aún no existe</h1>
                <p>La aplicación está instalada, pero falta importar el archivo SQL.</p>
                <ol>
                    <li>Abre <code>phpMyAdmin</code>.</li>
                    <li>Crea o importa el archivo <code>finanzas/sql/finanzas.sql</code>.</li>
                    <li>Recarga esta página.</li>
                </ol>
                <p>Detalle técnico: <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    die('Error de conexión a la base de datos: ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

function current_user_id(): int
{
    return (int) ($_SESSION['user']['id'] ?? 0);
}

function current_user_name(): string
{
    return $_SESSION['user']['nombre'] ?? 'Invitado';
}

function current_user_initials(): string
{
    $name = trim(current_user_name());
    if ($name === '') {
        return 'IN';
    }

    $parts = preg_split('/\s+/', $name) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }

    return $initials !== '' ? $initials : 'US';
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function format_currency(float $amount): string
{
    return '$ ' . number_format($amount, 0, ',', '.');
}

function badge_rgba(string $hex, float $alpha = 0.2): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) {
        return 'rgba(108, 99, 255, ' . $alpha . ')';
    }

    [$r, $g, $b] = sscanf($hex, '%02x%02x%02x');
    return sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, $alpha);
}

function obligation_progress_color(float $percentage): string
{
    if ($percentage < 30) {
        return 'var(--danger)';
    }

    if ($percentage <= 70) {
        return 'var(--warning)';
    }

    return 'var(--success)';
}

function obligation_status_label(string $status): string
{
    return match ($status) {
        'pagada' => 'Pagada',
        'congelada' => 'Congelada',
        default => 'Activa',
    };
}

function obligation_status_class(string $status): string
{
    return 'status-' . $status;
}

function obligation_days_remaining(?string $date): ?int
{
    if (!$date) {
        return null;
    }

    $today = new DateTimeImmutable('today');
    $limit = new DateTimeImmutable($date);
    return (int) $today->diff($limit)->format('%r%a');
}

function month_options(int $months = 12): array
{
    $options = [];
    $current = new DateTimeImmutable('first day of this month');
    for ($i = 0; $i < $months; $i++) {
        $date = $current->modify("-{$i} months");
        $options[$date->format('Y-m')] = $date->format('m/Y');
    }

    return $options;
}

function fetch_categories(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT * FROM categorias WHERE user_id = ? ORDER BY nombre');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function fetch_active_obligations(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT *, (monto_total - monto_pagado) AS saldo_pendiente FROM obligaciones WHERE user_id = ? AND estado = 'activa' ORDER BY fecha_limite IS NULL, fecha_limite ASC, created_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function validate_category_ownership(PDO $pdo, int $categoryId, int $userId): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM categorias WHERE id = ? AND user_id = ?');
    $stmt->execute([$categoryId, $userId]);
    return (bool) $stmt->fetchColumn();
}

function validate_obligation_ownership(PDO $pdo, int $obligationId, int $userId, bool $onlyActive = false): bool
{
    $sql = 'SELECT COUNT(*) FROM obligaciones WHERE id = ? AND user_id = ?';
    if ($onlyActive) {
        $sql .= " AND estado = 'activa'";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$obligationId, $userId]);
    return (bool) $stmt->fetchColumn();
}

function upsert_income(PDO $pdo, int $userId, array $data, ?int $incomeId = null): void
{
    if ($incomeId) {
        $stmt = $pdo->prepare('UPDATE ingresos SET descripcion = ?, monto = ?, fecha = ?, notas = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$data['descripcion'], $data['monto'], $data['fecha'], $data['notas'], $incomeId, $userId]);
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO ingresos (descripcion, monto, fecha, notas, user_id) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$data['descripcion'], $data['monto'], $data['fecha'], $data['notas'], $userId]);
}

function upsert_expense(PDO $pdo, int $userId, array $data, ?int $expenseId = null): void
{
    if ($expenseId) {
        $stmt = $pdo->prepare('UPDATE gastos SET descripcion = ?, monto = ?, fecha = ?, categoria_id = ?, obligacion_id = ?, es_pago_obligacion = ?, notas = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([
            $data['descripcion'],
            $data['monto'],
            $data['fecha'],
            $data['categoria_id'],
            $data['obligacion_id'],
            $data['es_pago_obligacion'],
            $data['notas'],
            $expenseId,
            $userId,
        ]);
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO gastos (descripcion, monto, fecha, categoria_id, obligacion_id, es_pago_obligacion, notas, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $data['descripcion'],
        $data['monto'],
        $data['fecha'],
        $data['categoria_id'],
        $data['obligacion_id'],
        $data['es_pago_obligacion'],
        $data['notas'],
        $userId,
    ]);
}

function fetch_dashboard_chart_data(PDO $pdo, int $userId): array
{
    $months = [];
    $labels = [];
    for ($i = 5; $i >= 0; $i--) {
        $date = (new DateTimeImmutable('first day of this month'))->modify("-{$i} months");
        $months[] = $date->format('Y-m');
        $labels[] = ucfirst($date->format('M y'));
    }

    $stmt = $pdo->prepare("SELECT DATE_FORMAT(fecha, '%Y-%m') AS month_key, SUM(monto) AS total FROM gastos WHERE user_id = ? AND fecha >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY month_key");
    $stmt->execute([$userId]);
    $monthly = [];
    foreach ($stmt->fetchAll() as $row) {
        $monthly[$row['month_key']] = (float) $row['total'];
    }

    $expenseSeries = [];
    foreach ($months as $month) {
        $expenseSeries[] = $monthly[$month] ?? 0;
    }

    $stmt = $pdo->prepare("SELECT c.nombre, c.color, SUM(g.monto) AS total FROM gastos g INNER JOIN categorias c ON c.id = g.categoria_id WHERE g.user_id = ? AND DATE_FORMAT(g.fecha, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') GROUP BY c.id, c.nombre, c.color ORDER BY total DESC");
    $stmt->execute([$userId]);
    $categoryRows = $stmt->fetchAll();

    return [
        'monthly' => [
            'labels' => $labels,
            'values' => $expenseSeries,
        ],
        'categories' => [
            'labels' => array_column($categoryRows, 'nombre'),
            'values' => array_map(static fn($row) => (float) $row['total'], $categoryRows),
            'colors' => array_column($categoryRows, 'color'),
        ],
    ];
}
