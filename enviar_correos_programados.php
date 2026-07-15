<?php
/**
 * enviar_correos_programados.php
 * -------------------------------------------------------
 * Script independiente para ejecutarse vía Cron Job.
 * Busca en 'correos_programados' los registros con:
 *   - estado = 'pendiente'
 *   - fecha_hora_envio <= NOW()
 * y los envía por correo. NO requiere sesión (se ejecuta
 * desde consola / cron, no desde el navegador).
 * -------------------------------------------------------
 */

// --- Evita que el script se cuelgue por límites de tiempo ---
set_time_limit(120);

// --- Ruta absoluta a conexion.php (ajusta si es necesario) ---
require_once __DIR__ . "/conexion.php";

// -------------------------------------------------------
// OPCIÓN A: usando PHPMailer (RECOMENDADO en producción)
// -------------------------------------------------------
// 1. Instala PHPMailer:  composer require phpmailer/phpmailer
// 2. Descomenta las siguientes líneas y el bloque de función enviarConPHPMailer()
// require __DIR__ . '/vendor/autoload.php';
// use PHPMailer\PHPMailer\PHPMailer;
// use PHPMailer\PHPMailer\Exception;

function enviarConPHPMailer($destino, $asunto, $cuerpo) {
    /*
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.tudominio.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'notificaciones@tudominio.com';
        $mail->Password   = 'TU_PASSWORD_AQUI';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('notificaciones@tudominio.com', 'MARCAJE CMD');
        $mail->addAddress($destino);

        $mail->isHTML(false);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpo;

        $mail->send();
        return [true, ''];
    } catch (Exception $e) {
        return [false, $mail->ErrorInfo];
    }
    */
    return [false, 'PHPMailer no configurado.'];
}

// -------------------------------------------------------
// OPCIÓN B: usando la función mail() nativa de PHP
// (requiere que el servidor tenga un MTA configurado,
//  ej. sendmail / postfix)
// -------------------------------------------------------
function enviarConMailNativo($destino, $asunto, $cuerpo) {
    $headers  = "From: MARCAJE CMD <notificaciones@tudominio.com>\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $enviado = mail($destino, $asunto, $cuerpo, $headers);

    return $enviado ? [true, ''] : [false, 'Fallo la función mail() nativa.'];
}

// =========================================================
// PROCESO PRINCIPAL
// =========================================================

$log = [];
$ahora = date('Y-m-d H:i:s');

$sql = "SELECT id, id_empleado, correo_destino, asunto, cuerpo, intentos
        FROM correos_programados
        WHERE estado = 'pendiente'
        AND fecha_hora_envio <= ?
        ORDER BY fecha_hora_envio ASC
        LIMIT 50";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "s", $ahora);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($resultado) === 0) {
    $log[] = "[$ahora] No hay correos pendientes por enviar.";
} else {
    while ($fila = mysqli_fetch_assoc($resultado)) {

        // --- Elige el método de envío: cambia esta línea si usas PHPMailer ---
        list($ok, $error) = enviarConMailNativo(
            $fila['correo_destino'],
            $fila['asunto'],
            $fila['cuerpo']
        );

        if ($ok) {
            $upd = mysqli_prepare($conexion, "
                UPDATE correos_programados
                SET estado = 'enviado', fecha_envio_real = NOW()
                WHERE id = ?
            ");
            mysqli_stmt_bind_param($upd, "i", $fila['id']);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            $log[] = "[$ahora] Enviado -> ID {$fila['id']} ({$fila['correo_destino']})";
        } else {
            $nuevos_intentos = (int) $fila['intentos'] + 1;
            $nuevo_estado = ($nuevos_intentos >= 3) ? 'fallido' : 'pendiente';

            $upd = mysqli_prepare($conexion, "
                UPDATE correos_programados
                SET intentos = ?, estado = ?, error_detalle = ?
                WHERE id = ?
            ");
            mysqli_stmt_bind_param($upd, "issi", $nuevos_intentos, $nuevo_estado, $error, $fila['id']);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            $log[] = "[$ahora] ERROR -> ID {$fila['id']} ({$fila['correo_destino']}): $error";
        }
    }
}

mysqli_stmt_close($stmt);
mysqli_close($conexion);

// --- Log en archivo de texto (útil para depurar el cron) ---
$ruta_log = __DIR__ . "/logs_correos_programados.txt";
file_put_contents($ruta_log, implode(PHP_EOL, $log) . PHP_EOL, FILE_APPEND);

// --- Salida por consola (visible si se ejecuta manualmente) ---
echo implode(PHP_EOL, $log) . PHP_EOL;
