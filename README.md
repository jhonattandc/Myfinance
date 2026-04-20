# Finanzas

Sistema de finanzas personales en PHP 8 + MySQL + HTML/CSS/JS vanilla, listo para ejecutarse en XAMPP.

## Instalación

1. Instala XAMPP y activa `Apache` + `MySQL`.
2. Copia la carpeta `finanzas/` a `C:/xampp/htdocs/`.
3. Importa después el archivo `finanzas/sql/finanzas.sql` en `phpMyAdmin`.
4. Visita `http://localhost/finanzas/`.

## Credenciales demo

- Usuario visible: `WanDu`
- Email login: `wandu@demo.com`
- Contraseña: `Banesco15*`

## Estructura

- `index.php`: redirección inicial.
- `login.php`: autenticación con sesiones PHP y `password_verify()`.
- `dashboard.php`: KPIs, obligaciones, gráficas y últimos gastos.
- `ingresos.php`: CRUD de ingresos.
- `gastos.php`: CRUD de gastos con filtros y vínculo a obligaciones.
- `cartera.php`: gestión de dinero prestado pendiente por ingresar.
- `obligaciones.php`: gestión completa de deuda y pagos rápidos.
- `categorias.php`: CRUD de categorías.
- `assets/style.css`: UI dark mode responsive.
- `assets/charts.js`: interacción frontend, toasts, sidebar y Chart.js.

## Notas

- `user_id` siempre se toma desde la sesión.
- Todas las consultas usan `PDO` con sentencias preparadas.
- El login no muestra credenciales en pantalla; quedaron documentadas aquí en el README.
- El `finanzas.sql` deja la app sin movimientos precargados para que ingreses datos reales desde cero.
- El dashboard contempla `cartera` como dinero pendiente por ingresar y muestra el balance proyectado.
