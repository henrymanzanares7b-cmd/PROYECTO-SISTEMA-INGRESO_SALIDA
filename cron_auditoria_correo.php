<?php
// cron_auditoria_correo.php
// Este script está diseñado para ejecutarse en segundo plano.

// Importar clases de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Requerir el autoloader de Composer y tu conexión
require 'vendor/autoload.php';
require 'conexion.php';

// --- 1. MANEJO DE ERRORES Y LOGS ---
$log_file = __DIR__ . '/logs_auditoria.txt';
function registrar_log($mensaje) {
    global $log_file;
    $fecha = date('Y-m-d H:i:s');
    error_log("[$fecha] $mensaje\n", 3, $log_file);
}

// --- 2. VARIABLES DE ENTORNO ---
// Parseamos el archivo .env de forma nativa
$env = parse_ini_file(__DIR__ . '/.env');
if (!$env) {
    registrar_log("ERROR CRÍTICO: No se pudo cargar el archivo .env");
    exit();
}

// --- 3. CONSULTA SQL ---
// Buscamos empleados activos sin asistencia ni permisos, QUE TENGAN CORREO
$sql_auditoria = "
    SELECT id_empleado, nombre_completo, correo 
    FROM empleados 
    WHERE estado = 'Activo' 
    AND correo IS NOT NULL 
    AND correo != ''
    AND NOT EXISTS (
        SELECT 1 FROM registros_asistencia 
        WHERE registros_asistencia.id_empleado = empleados.id_empleado 
        AND fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    )
";

// Lógica de permisos (igual a tu archivo original)
$tabla_permisos_existe = mysqli_query($conexion, "SHOW TABLES LIKE 'permisos_empleados'");
if (mysqli_num_rows($tabla_permisos_existe) > 0) {
    $sql_auditoria .= "
        AND NOT EXISTS (
            SELECT 1 FROM permisos_empleados 
            WHERE permisos_empleados.id_empleado = empleados.id_empleado 
            AND fecha_fin >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        )
    ";
}

$resultado = mysqli_query($conexion, $sql_auditoria);

if (!$resultado) {
    registrar_log("ERROR DB: " . mysqli_error($conexion));
    exit();
}

if (mysqli_num_rows($resultado) === 0) {
    registrar_log("INFO: Auditoría ejecutada. Todos al día, no se enviaron correos.");
    exit();
}

// --- 4. CONFIGURACIÓN Y ENVÍO DE CORREOS ---
$mail = new PHPMailer(true);

try {
    // Configuración del servidor SMTP
    $mail->isSMTP();
    $mail->Host       = $env['SMTP_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $env['SMTP_USER'];
    $mail->Password   = $env['SMTP_PASS'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $env['SMTP_PORT'];
    $mail->setFrom($env['SMTP_USER'], 'Sist.Control - Auditoría');

    $enviados = 0;

    // Iterar sobre los colaboradores
    while ($fila = mysqli_fetch_assoc($resultado)) {
        $nombre = $fila['nombre_completo'];
        $correo = trim($fila['correo']);

        try {
            $mail->clearAddresses();
            $mail->addAddress($correo, $nombre);
            
            $mail->isHTML(true);
            $mail->Subject = 'Aviso de Inasistencia - MARCAJE CMD';
            $mail->Body    = "Hola <b>$nombre</b>,<br><br>Notamos que no has registrado asistencia en MARCAJE CMD durante la última semana. ¿Todo bien?<br><br>Saludos cordiales.";
            $mail->AltBody = "Hola $nombre,\n\nNotamos que no has registrado asistencia en MARCAJE CMD durante la última semana. ¿Todo bien?\n\nSaludos cordiales.";

            $mail->send();
            $enviados++;
        } catch (Exception $e) {
            registrar_log("ERROR EMAIL: Fallo al enviar a $correo. Mailer Error: {$mail->ErrorInfo}");
        }
    }
    
    registrar_log("ÉXITO: Se enviaron $enviados correos de auditoría.");

} catch (Exception $e) {
    registrar_log("ERROR CRÍTICO SMTP: No se pudo conectar al servidor. Mailer Error: {$mail->ErrorInfo}");
}
?>