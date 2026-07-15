<?php
// Configurar zona horaria y conexión
date_default_timezone_set('America/El_Salvador');
require_once("conexion.php");

// --- AUTO-MIGRACIÓN PARA ALMUERZOS Y PERMISOS ---
$check_cols = mysqli_query($conexion, "SHOW COLUMNS FROM registros_asistencia LIKE 'salida_almuerzo'");
if (mysqli_num_rows($check_cols) === 0) {
    mysqli_query($conexion, "ALTER TABLE registros_asistencia 
        ADD COLUMN salida_almuerzo TIME NULL AFTER hora_ingreso,
        ADD COLUMN regreso_almuerzo TIME NULL AFTER salida_almuerzo");
}

$check_cols_permiso = mysqli_query($conexion, "SHOW COLUMNS FROM registros_asistencia LIKE 'salida_permiso'");
if (mysqli_num_rows($check_cols_permiso) === 0) {
    mysqli_query($conexion, "ALTER TABLE registros_asistencia 
        ADD COLUMN salida_permiso TIME NULL AFTER regreso_almuerzo,
        ADD COLUMN regreso_permiso TIME NULL AFTER salida_permiso");
}

// --- FUNCIÓN DE ALERTA VISUAL Y SONORA ---
function mostrarAlerta($tipo, $icono, $titulo, $mensaje, $badge_class, $estado) {
    $hora_am_pm = date('h:i A'); 

    $bg_color = match($tipo) {
        'exito' => 'bg-success',
        'aviso' => 'bg-warning',
        'error' => 'bg-danger',
        'info'  => 'bg-info',
        default => 'bg-primary'
    };
    $text_color = ($tipo == 'aviso' || $tipo == 'info') ? 'text-dark' : 'text-white';
    
    // Al imprimir esta alerta se detiene el renderizado del formulario e imprime la tarjeta full-screen
    echo "<!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Sist.Control - Validación</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
        <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css'>
        <style>
            body { background-color: #f8f9fa; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; font-family: system-ui, -apple-system, sans-serif; }
            .card-alerta { border: none; border-radius: 1rem; box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1); max-width: 500px; width: 90%; text-align: center; overflow: hidden; }
            .icono-estado { font-size: 4rem; margin-bottom: 1rem; }
        </style>
    </head>
    <body>
        <div class='card card-alerta'>
            <div class='card-header $bg_color $text_color py-3 border-0'>
                <h4 class='mb-0 fw-bold'>$titulo</h4>
            </div>
            <div class='card-body p-4 bg-white'>
                <div class='icono-estado text-".($tipo == 'aviso' ? 'warning' : ($tipo == 'error' ? 'danger' : 'success'))."'>
                    <i class='bi $icono'></i>
                </div>
                
                <h2 class='fw-bold text-dark mb-3'>⌚ $hora_am_pm</h2>
                
                <p class='fs-5 text-secondary'>$mensaje</p>
                ".($estado ? "<span class='badge rounded-pill $badge_class fs-6 px-3 py-2 mt-2 shadow-sm'>$estado</span>" : "")."
            </div>
            <div class='card-footer bg-light border-0 py-3'>
                <a href='marcar_web.php' class='btn btn-outline-secondary rounded-pill px-4'>Volver</a>
            </div>
        </div>
        
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Generador de Web Audio (Beeps)
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                function beep(freq, dur) {
                    const osc = ctx.createOscillator(), gain = ctx.createGain();
                    osc.connect(gain); gain.connect(ctx.destination);
                    osc.frequency.value = freq; osc.start();
                    gain.gain.exponentialRampToValueAtTime(0.00001, ctx.currentTime + dur);
                    setTimeout(() => osc.stop(), dur * 1000);
                }
                
                // Disparar sonido dependiendo del tipo de alerta
                const tipo = '$tipo';
                if(tipo === 'exito' || tipo === 'info') { 
                    beep(800, 0.3); 
                } else { 
                    beep(400, 0.15); 
                    setTimeout(() => beep(400, 0.15), 200); 
                }
                
                // Redirigir al formulario limpio después de 2.5 segundos
                setTimeout(function() {
                    window.location.href = 'marcar_web.php';
                }, 2500);
            });
        </script>
    </body>
    </html>";
    exit;
}

// --- PROCESAMIENTO AUTOMÁTICO (MOTOR LÓGICO) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty(trim($_POST['id_empleado']))) {
    
    $id_empleado = mysqli_real_escape_string($conexion, trim($_POST['id_empleado']));
    
    $query_empleado = "SELECT e.nombre_completo, h.hora_entrada, h.hora_salida 
                       FROM empleados e
                       LEFT JOIN horarios h ON e.id_horario = h.id_horario
                       WHERE e.id_empleado = '$id_empleado' AND e.estado = 'Activo'";
    $result_emp = mysqli_query($conexion, $query_empleado);
    
    if (mysqli_num_rows($result_emp) > 0) {
        $empleado = mysqli_fetch_assoc($result_emp);
        $nombre = $empleado['nombre_completo'];
        $hora_prog_entrada = $empleado['hora_entrada'];
        $hora_prog_salida = $empleado['hora_salida'];
        
        $fecha_actual = date('Y-m-d');
        $hora_actual = date('H:i:s');
        $hora_actual_sec = strtotime($hora_actual);

        // Consultar permisos activos
        $query_permiso = "SELECT * FROM solicitudes_permisos 
                          WHERE id_empleado = '$id_empleado' 
                          AND estado = 'Aprobado' 
                          AND '$fecha_actual' BETWEEN fecha_inicio AND fecha_fin 
                          LIMIT 1";
        $result_permiso = mysqli_query($conexion, $query_permiso);
        $permiso_hoy = mysqli_fetch_assoc($result_permiso);

        // Consultar registros del día
        $query_hoy = "SELECT * FROM registros_asistencia WHERE id_empleado = '$id_empleado' AND fecha = '$fecha_actual'";
        $result_hoy = mysqli_query($conexion, $query_hoy);

        if (mysqli_num_rows($result_hoy) == 0) {
            // 1. MARCA DE ENTRADA INICIAL
            $estado_entrada = 'A Tiempo';
            $badge_class = 'text-bg-success';
            
            if ($permiso_hoy && $permiso_hoy['modalidad'] === 'Día completo') {
                $estado_entrada = 'Permiso Día Completo Justificado';
                $badge_class = 'text-bg-info';
            } else {
                if ($hora_prog_entrada) {
                    $tolerancia = 5 * 60; // 5 mins
                    if ($hora_actual_sec > (strtotime($hora_prog_entrada) + $tolerancia)) {
                        $estado_entrada = 'Llegada Tarde';
                        $badge_class = 'text-bg-danger';
                    }
                }
            }

            // Etiqueta para marcar el origen Web
            $estado_entrada .= ' (Web)';

            $query_insert = "INSERT INTO registros_asistencia (id_empleado, fecha, hora_ingreso, tipo_marca, estado_marca) 
                             VALUES ('$id_empleado', '$fecha_actual', '$hora_actual', 'Entrada', '$estado_entrada')";
            
            if (mysqli_query($conexion, $query_insert)) {
                if ($permiso_hoy && $permiso_hoy['modalidad'] === 'Día completo') {
                    mostrarAlerta('info', 'bi-shield-check', 'Ingreso Justificado', "Hola <b>$nombre</b>, tu día está cubierto por un permiso.", $badge_class, $estado_entrada);
                } else {
                    mostrarAlerta('exito', 'bi-check-circle-fill', 'Ingreso Registrado', "Hola <b>$nombre</b>, buen turno.", $badge_class, $estado_entrada);
                }
            } else {
                mostrarAlerta('error', 'bi-x-octagon-fill', 'Error DB', mysqli_error($conexion), '', '');
            }

        } else {
            $registro = mysqli_fetch_assoc($result_hoy);
            $id_registro = $registro['id_registro'];
            $estado_actual = $registro['estado_marca'];
            
            // Si tiene permiso de día completo
            if ($permiso_hoy && $permiso_hoy['modalidad'] === 'Día completo' && $registro['hora_salida'] == NULL) {
                $estado_final = $estado_actual . " | Salida Normal (Permiso) (Web)";
                $query_update = "UPDATE registros_asistencia SET hora_salida = '$hora_actual', tipo_marca = 'Salida', estado_marca = '$estado_final' WHERE id_registro = '$id_registro'";
                mysqli_query($conexion, $query_update);
                mostrarAlerta('aviso', 'bi-info-circle-fill', 'Permiso Activo', "<b>$nombre</b>, tienes permiso de día completo hoy. Turno cerrado.", 'text-bg-info', 'Día Justificado');
            }

            $tiene_permiso_horas = ($permiso_hoy && $permiso_hoy['modalidad'] === 'Por horas');

            if ($tiene_permiso_horas && $registro['salida_permiso'] == NULL) {
                // 2. MARCA SALIDA DE PERMISOS POR HORAS
                $estado_final = $estado_actual . " | Salió con permiso (Web)";
                $hora_regreso_estimada = date("h:i A", strtotime($permiso_hoy['hora_fin']));

                $query_update = "UPDATE registros_asistencia SET salida_permiso = '$hora_actual', estado_marca = '$estado_final' WHERE id_registro = '$id_registro'";
                if (mysqli_query($conexion, $query_update)) {
                    mostrarAlerta('info', 'bi-door-open-fill', 'Salió con Permiso', "<b>$nombre</b>, registramos tu salida. Recuerda regresar a las <b>$hora_regreso_estimada</b>.", 'text-bg-info', 'En Permiso');
                }
            } elseif ($tiene_permiso_horas && $registro['salida_permiso'] != NULL && $registro['regreso_permiso'] == NULL) {
                // 3. MARCA REGRESO DE PERMISO POR HORAS
                $estado_permiso = 'Regresó de permiso (A tiempo)';
                $badge_class = 'text-bg-success';
                
                $hora_fin_permiso = strtotime($permiso_hoy['hora_fin']) + 59 ;
                
                if ($hora_actual_sec > $hora_fin_permiso) { 
                    $estado_permiso = 'Regresó de permiso (Tarde)';
                    $badge_class = 'text-bg-danger';
                }

                $estado_final = str_replace(" | Salió con permiso (Web)", "", $estado_actual);
                $estado_final = str_replace(" | Salió con permiso", "", $estado_final) . " | " . $estado_permiso . " (Web)";
                
                $query_update = "UPDATE registros_asistencia SET regreso_permiso = '$hora_actual', estado_marca = '$estado_final' WHERE id_registro = '$id_registro'";
                if (mysqli_query($conexion, $query_update)) {
                    mostrarAlerta('exito', 'bi-person-check-fill', 'Regresó de Permiso', "Bienvenido de vuelta, <b>$nombre</b>. Tu regreso ha sido registrado.", $badge_class, $estado_permiso);
                }

            } elseif ($registro['salida_almuerzo'] != NULL && $registro['regreso_almuerzo'] == NULL) {
                // 4. MARCA REGRESO DE ALMUERZO
                $estado_almuerzo = 'Almuerzo a Tiempo';
                $badge_class = 'text-bg-success';
                
                $diff_almuerzo = $hora_actual_sec - strtotime($registro['salida_almuerzo']);
                if ($diff_almuerzo > 3600) { 
                    $estado_almuerzo = 'Excedió tiempo de almuerzo';
                    $badge_class = 'text-bg-danger';
                }

                $estado_final = str_replace(" | En Almuerzo (Web)", "", $estado_actual);
                $estado_final = str_replace(" | En Almuerzo", "", $estado_final) . " | " . $estado_almuerzo . " (Web)";
                
                $query_update = "UPDATE registros_asistencia SET regreso_almuerzo = '$hora_actual', estado_marca = '$estado_final' WHERE id_registro = '$id_registro'";
                if (mysqli_query($conexion, $query_update)) {
                    mostrarAlerta('exito', 'bi-person-check-fill', 'Regreso de Almuerzo', "Bienvenido de vuelta, <b>$nombre</b>.", $badge_class, $estado_almuerzo);
                }
            } elseif ($registro['hora_salida'] == NULL) {
                // 5. MARCA DE SALIDA FINAL
                $estado_salida = 'Salida Normal';
                $badge_class = 'text-bg-primary';
                
                if ($hora_prog_salida) {
                    if ($hora_actual_sec < strtotime($hora_prog_salida)) {
                        $estado_salida = 'Salida Temprana';
                        $badge_class = 'text-bg-warning text-dark';
                    } elseif ($hora_actual_sec > (strtotime($hora_prog_salida) + (15 * 60))) {
                        $estado_salida = 'Horas Extra';
                        $badge_class = 'text-bg-success';
                    }
                }

                $estado_final = $estado_actual . " | " . $estado_salida . " (Web)";
                $query_update = "UPDATE registros_asistencia SET hora_salida = '$hora_actual', tipo_marca = 'Salida', estado_marca = '$estado_final' WHERE id_registro = '$id_registro'";
                
                if (mysqli_query($conexion, $query_update)) {
                    mostrarAlerta('exito', 'bi-box-arrow-right', 'Salida Final Registrada', "Hasta luego, <b>$nombre</b>. ¡Buen descanso!", $badge_class, $estado_salida);
                }
            } else {
                mostrarAlerta('aviso', 'bi-exclamation-triangle-fill', 'Jornada Completada', "<b>$nombre</b>, ya completaste todos tus registros de hoy.", 'text-bg-secondary', 'Turno Finalizado');
            }
        }
    } else {
        mostrarAlerta('error', 'bi-person-x-fill', 'Acceso Denegado', "Código no encontrado o empleado inactivo.", '', '');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sist.Control - Auto Marcaje Web</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body { 
            background-color: #f4f6f9; 
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 20px 0;
        }
        .main-container {
            width: 100%;
            max-width: 550px;
        }
        .card-custom {
            background: #ffffff;
            border-radius: 1rem;
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }
        #lector-qr {
            border: none !important;
            border-radius: 0.8rem;
            overflow: hidden;
        }
        #lector-qr video {
            border-radius: 0.8rem;
        }
        .input-group-lg {
            border-radius: 50rem;
            overflow: hidden;
            border: 1px solid #dee2e6;
        }
        .form-control-custom {
            border: none;
            background: transparent;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .form-control-custom:focus {
            box-shadow: none;
            background: transparent;
        }
        .form-control-custom::placeholder {
            text-transform: none;
            letter-spacing: normal;
        }
        .btn-blue-primary {
            background-color: #0d6efd;
            border-radius: 0 50rem 50rem 0 !important;
            color: white;
            font-weight: 600;
            padding-left: 1.5rem;
            padding-right: 1.5rem;
        }
        .btn-blue-primary:hover {
            background-color: #0b5ed7;
        }
    </style>
</head>
<body>

    <div class="main-container text-center px-3">
        <div class="mb-3">
            <p class="text-muted mb-0">Acerca el código QR a la cámara para registrar tus marcajes</p>
        </div>
        
        <div class="card card-custom p-4">
            <div id="lector-qr" class="mb-4 bg-white shadow-sm border"></div>
            
            <hr class="text-muted opacity-25 mb-4">
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" autocomplete="off" id="form-marcaje">
                <label class="form-label text-muted fw-semibold small text-start w-100 mb-2">Ingreso Manual (Respaldo)</label>
                
                <div class="input-group input-group-lg bg-white shadow-sm">
                    <span class="input-group-text bg-transparent border-0 ps-3 pe-2">
                        <i class="bi bi-keyboard text-muted"></i>
                    </span>
                    <input type="text" 
                           class="form-control form-control-custom" 
                           id="id_empleado" 
                           name="id_empleado" 
                           placeholder="Ej. EMP-001" 
                           required>
                    <button class="btn btn-blue-primary" type="submit">
                        Marcar
                    </button>
                </div>
            </form>
        </div>
        
        <div class="mt-4 text-muted small">
            Sist.Control &copy; <?php echo date('Y'); ?>
        </div>
    </div>

    <script>
        // Configuración del Escáner QR
        function onScanSuccess(decodedText, decodedResult) {
            // Detiene el escáner
            html5QrcodeScanner.clear();
            
            // Asigna el valor al input y envía el formulario automáticamente
            document.getElementById('id_empleado').value = decodedText;
            
            const form = document.getElementById('form-marcaje');
            const btn = form.querySelector('button[type="submit"]');
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            btn.disabled = true;
            
            form.submit();
        }
        
        // Inicializa el lector QR
        let html5QrcodeScanner = new Html5QrcodeScanner(
            "lector-qr", 
            { fps: 15, qrbox: {width: 250, height: 250} }, 
            false
        );
        html5QrcodeScanner.render(onScanSuccess);

        // Forzar mayúsculas en el input manual
        document.getElementById('id_empleado').addEventListener('input', function (e) {
            e.target.value = e.target.value.toUpperCase();
        });
        
        // Evitar doble envío en ingreso manual
        document.getElementById('form-marcaje').addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        });
    </script>
</body>
</html>