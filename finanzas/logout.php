<?php
require_once __DIR__ . '/config/db.php';

session_unset();
session_destroy();
session_start();
flash('success', 'Sesión cerrada correctamente.');
redirect('login.php');
