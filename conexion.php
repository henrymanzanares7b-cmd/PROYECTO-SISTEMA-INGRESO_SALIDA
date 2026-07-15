<?php
// Iniciamos la sesión para proteger las páginas privadas
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = "sql204.infinityfree.com"; // Reemplaza esto con tu "MySQL Hostname" exacto del panel.
$user = "if0_42401370"; // Este es el usuario de tu cuenta que vimos en la captura anterior.
$password = "0sV2WsktF7Yv5OQ"; // Escribe la contraseña de tu cuenta de InfinityFree.
$db = "if0_42401370_crud"; // El nombre de tu BD (InfinityFree siempre le añade tu usuario al principio).

$conexion = mysqli_connect($host, $user, $password, $db);

if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
?>