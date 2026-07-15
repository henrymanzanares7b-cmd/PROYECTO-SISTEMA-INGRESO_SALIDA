<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include("conexion.php");

// --- Seguridad: sesión activa ---
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'Sesión no válida. Vuelve a iniciar sesión.']);
    exit();
}

// --- Auto-migración: asegura que la tabla exista ---
$check_tabla = mysqli_query($conexion, "SHOW TABLES LIKE 'correos_programados'");
if (mysqli_num_rows($check_tabla) === 0) {
    mysqli_query($conexion, "
        CREATE TABLE correos_programados (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_empleado VARCHAR(50) NOT NULL,
            correo_destino VARCHAR(100) NOT NULL,
            asunto VARCHAR(255) NOT NULL,
            cuerpo TEXT NOT NULL,
            fecha_hora_envio DATETIME NOT NULL,
            estado ENUM('pendiente','enviado','fallido') NOT NULL DEFAULT 'pendiente',
            intentos TINYINT UNSIGNED NOT NULL DEFAULT 0,
            error_detalle VARCHAR(255) NULL,
            fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            fecha_envio_real DATETIME NULL,
            INDEX idx_estado_fecha (estado, fecha_hora_envio),
            INDEX idx_id_empleado (id_empleado)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// --- Lectura del body JSON enviado por fetch() ---
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Datos no válidos.']);
    exit();
}

$id_empleado      = isset($input['id_empleado']) ? trim($input['id_empleado']) : '';
$correo_destino   = isset($input['correo_destino']) ? trim($input['correo_destino']) : '';
$nombre_empleado  = isset($input['nombre_empleado']) ? trim($input['nombre_empleado']) : '';
$fecha_hora_envio = isset($input['fecha_hora_envio']) ? trim($input['fecha_hora_envio']) : '';

// --- Validaciones ---
if ($id_empleado === '') {
    echo json_encode(['ok' => false, 'mensaje' => 'Empleado no válido.']);
    exit();
}

if (!filter_var($correo_destino, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'mensaje' => 'El correo electrónico no es válido.']);
    exit();
}

// El input datetime-local llega como "YYYY-MM-DDTHH:MM"
$fecha_hora_envio = str_replace('T', ' ', $fecha_hora_envio);
$dt = DateTime::createFromFormat('Y-m-d H:i', $fecha_hora_envio);
if (!$dt) {
    echo json_encode(['ok' => false, 'mensaje' => 'La fecha y hora no tienen un formato válido.']);
    exit();
}

$ahora = new DateTime();
if ($dt < $ahora) {
    echo json_encode(['ok' => false, 'mensaje' => 'La fecha programada debe ser posterior a la hora actual.']);
    exit();
}

$fecha_hora_sql = $dt->format('Y-m-d H:i:s');

// --- Contenido del correo ---
$asunto = "Aviso de Inasistencia - MARCAJE CMD";
$cuerpo = "Hola " . $nombre_empleado . ",\n\n"
        . "Notamos que no has registrado asistencia en MARCAJE CMD durante la última semana. "
        . "¿Todo bien?\n\nSaludos.";

// --- Inserción segura con prepared statement ---
$stmt = mysqli_prepare($conexion, "
    INSERT INTO correos_programados
        (id_empleado, correo_destino, asunto, cuerpo, fecha_hora_envio, estado)
    VALUES (?, ?, ?, ?, ?, 'pendiente')
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al preparar la consulta: ' . mysqli_error($conexion)]);
    exit();
}

mysqli_stmt_bind_param($stmt, "sssss", $id_empleado, $correo_destino, $asunto, $cuerpo, $fecha_hora_sql);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'ok' => true,
        'mensaje' => 'Correo programado correctamente para el ' . $dt->format('d/m/Y') . ' a las ' . $dt->format('H:i') . '.'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'Error al guardar: ' . mysqli_stmt_error($stmt)]);
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);
