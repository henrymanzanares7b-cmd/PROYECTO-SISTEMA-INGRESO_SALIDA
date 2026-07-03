<?php
// Iniciamos la sesión para proteger las páginas privadas
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = "localhost";
$user = "root";
$password = ""; 
$db = "crud"; // <-- REEMPLAZA CON EL NOMBRE DE TU BASE DE DATOS

$conexion = mysqli_connect($host, $user, $password, $db);

if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
?>