<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Asistencia</title>
    <style>
        /* Estilos generales para centrar TODO en la pantalla */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            display: flex;
            justify-content: center; /* Centra horizontalmente */
            align-items: center;     /* Centra verticalmente */
            height: 100vh;           /* Ocupa el 100% del alto de la pantalla */
            margin: 0;
        }
        
        /* Estilo base para la tarjeta de notificación */
        .alerta {
            padding: 30px 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            font-size: 1.3rem;
            text-align: center;
            max-width: 500px;
            width: 90%;
            background-color: white;
            line-height: 1.5;
        }

        /* Colores según el estado del mensaje */
        .alerta-exito {
            background-color: #d4edda;
            color: #155724;
            border-bottom: 6px solid #28a745;
        }
        .alerta-aviso {
            background-color: #fff3cd;
            color: #856404;
            border-bottom: 6px solid #ffc107;
        }
        .alerta-error {
            background-color: #f8d7da;
            color: #721c24;
            border-bottom: 6px solid #dc3545;
        }
        .alerta-info {
            background-color: #cce5ff;
            color: #004085;
            border-bottom: 6px solid #007bff;
        }

        /* Estilos para resaltar texto */
        .alerta strong { font-size: 1.6rem; display: block; margin-bottom: 12px; }
        .estado-badge {
            display: inline-block;
            background: rgba(255,255,255,0.8);
            padding: 8px 18px;
            border-radius: 25px;
            margin-top: 15px;
            font-weight: bold;
            border: 1px solid rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<?php
// Configuramos la hora local
date_default_timezone_set('America/El_Salvador');

include 'conexion.php';

if (isset($_POST['id_empleado']) || isset($_POST['qr_data'])) {
    $id_empleado = mysqli_real_escape_string($conexion, $_POST['qr_data'] ?? $_POST['id_empleado']);
    
    // 1. Obtener datos del empleado y su horario asignado
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

        // 2. Buscamos si el empleado ya tiene un registro creado el día de HOY
        $query_hoy = "SELECT * FROM registros_asistencia 
                      WHERE id_empleado = '$id_empleado' AND fecha = '$fecha_actual'";
        $result_hoy = mysqli_query($conexion, $query_hoy);

        // ==========================================
        // CASO A: NO HAY REGISTRO HOY -> ES ENTRADA
        // ==========================================
        if (mysqli_num_rows($result_hoy) == 0) {
            
            $estado_entrada = 'A tiempo';
            
            // Calculamos si llegó tarde (Tolerancia de 10 minutos)
            if ($hora_prog_entrada) {
                $tolerancia = 10 * 60; 
                if (strtotime($hora_actual) > (strtotime($hora_prog_entrada) + $tolerancia)) {
                    $estado_entrada = 'Llegada Tarde';
                }
            }

            // INSERTAMOS LA FILA CON LA HORA DE INGRESO
            $query_insert = "INSERT INTO registros_asistencia (id_empleado, fecha, hora_ingreso, tipo_marca, estado_marca) 
                             VALUES ('$id_empleado', '$fecha_actual', '$hora_actual', 'Entrada', '$estado_entrada')";
            
            if (mysqli_query($conexion, $query_insert)) {
                echo "<div class='alerta alerta-exito'>
                        <strong>✅ ¡Ingreso registrado!</strong>
                        Hola <b>$nombre</b>.<br>
                        <span class='estado-badge'>Estado: $estado_entrada</span>
                      </div>";
            } else {
                echo "<div class='alerta alerta-error'>
                        <strong>❌ Error en base de datos</strong>
                        " . mysqli_error($conexion) . "
                      </div>";
            }

        // ==========================================
        // CASO B: YA HAY REGISTRO HOY -> ES SALIDA
        // ==========================================
        } else {
            $registro = mysqli_fetch_assoc($result_hoy);
            $id_registro = $registro['id_registro'];
            
            // Verificamos que la hora de salida aún esté vacía (NULL)
            if ($registro['hora_salida'] == NULL) {
                
                $estado_salida = 'Salida Normal';
                
                // Calculamos si se fue tarde / hizo horas extra (Margen de 15 minutos)
                if ($hora_prog_salida) {
                    $margen_salida = 15 * 60; 
                    if (strtotime($hora_actual) > (strtotime($hora_prog_salida) + $margen_salida)) {
                        $estado_salida = 'Horas Extra / Salió Tarde';
                    }
                }

                $estado_final = $registro['estado_marca'] . " | " . $estado_salida;

                // ACTUALIZAMOS LA FILA EXISTENTE CON LA HORA DE SALIDA
                $query_update = "UPDATE registros_asistencia 
                                 SET hora_salida = '$hora_actual', 
                                     tipo_marca = 'Salida', 
                                     estado_marca = '$estado_final' 
                                 WHERE id_registro = '$id_registro'";
                
                if (mysqli_query($conexion, $query_update)) {
                    echo "<div class='alerta alerta-exito'>
                            <strong>👋 ¡Salida registrada!</strong>
                            Adiós <b>$nombre</b>.<br>
                            <span class='estado-badge'>Estado: $estado_salida</span>
                          </div>";
                } else {
                    echo "<div class='alerta alerta-error'>
                            <strong>❌ Error en base de datos</strong>
                            " . mysqli_error($conexion) . "
                          </div>";
                }

            } else {
                echo "<div class='alerta alerta-aviso'>
                        <strong>⚠️ Atención</strong>
                        El empleado <b>$nombre</b> ya completó su registro de entrada y salida de hoy.
                      </div>";
            }
        }
    } else {
        echo "<div class='alerta alerta-error'>
                <strong>🚫 Acceso Denegado</strong>
                Empleado no encontrado o se encuentra inactivo.
              </div>";
    }
} else {
    echo "<div class='alerta alerta-info'>
            <strong>🔍 Código no detectado</strong>
            Por favor, escanea un código QR válido.
          </div>";
}
?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Detectamos qué tipo de alerta generó PHP
        const esExito = document.querySelector('.alerta-exito');
        const esError = document.querySelector('.alerta-error') || 
                        document.querySelector('.alerta-aviso') || 
                        document.querySelector('.alerta-info');

        // Solo inicializamos el audio si hay un mensaje en pantalla
        if (esExito || esError) {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            const audioCtx = new AudioContext();

            function reproducirTono(frecuencia, duracion) {
                if (audioCtx.state === 'suspended') {
                    audioCtx.resume();
                }
                const oscilador = audioCtx.createOscillator();
                const ganancia = audioCtx.createGain();
                
                oscilador.type = 'sine'; 
                oscilador.frequency.value = frecuencia;
                
                oscilador.connect(ganancia);
                ganancia.connect(audioCtx.destination);
                
                oscilador.start();
                ganancia.gain.exponentialRampToValueAtTime(0.00001, audioCtx.currentTime + duracion);
                
                setTimeout(() => {
                    oscilador.stop();
                }, duracion * 1000);
            }

            // Reproducimos el sonido según el resultado
            if (esExito) {
                reproducirTono(800, 0.3); // 1 pitido de éxito
            } else if (esError) {
                reproducirTono(400, 0.15); // 2 pitidos de error
                setTimeout(() => {
                    reproducirTono(400, 0.15); 
                }, 200);
            }
        }
    });
</script>

</body>
</html>