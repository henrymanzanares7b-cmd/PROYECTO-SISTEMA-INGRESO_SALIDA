<?php
include("conexion.php");
if(!isset($_SESSION['usuario'])) { header("Location: login.php"); exit(); }

date_default_timezone_set('America/El_Salvador');

$mensaje = "";
$tipo_alerta = "";

if(isset($_POST['codigo_empleado'])) {
    $codigo = mysqli_real_escape_string($conexion, trim($_POST['codigo_empleado']));
    $fecha_hoy = date("Y-m-d");
    $hora_actual = date("H:i:s");

    $sql_validar = "SELECT nombre_completo FROM empleados WHERE id_empleado = '$codigo' AND estado = 'Activo'";
    $resultado_validar = mysqli_query($conexion, $sql_validar);

    if(mysqli_num_rows($resultado_validar) > 0) {
        $nombre = mysqli_fetch_assoc($resultado_validar)['nombre_completo'];

        $sql_buscar = "SELECT * FROM registros_asistencia WHERE id_empleado = '$codigo' AND fecha = '$fecha_hoy'";
        $resultado_asistencia = mysqli_query($conexion, $sql_buscar);

        if(mysqli_num_rows($resultado_asistencia) > 0) {
            $registro = mysqli_fetch_assoc($resultado_asistencia);
            
            if($registro['hora_salida'] == NULL) {
                // Registrar Salida
                $sql_salida = "UPDATE registros_asistencia SET hora_salida = '$hora_actual' WHERE id_empleado = '$codigo' AND fecha = '$fecha_hoy'";
                mysqli_query($conexion, $sql_salida);
                $mensaje = "¡Adiós <b>$nombre</b>! Salida registrada a las $hora_actual";
                $tipo_alerta = "alert-info";
            } else {
                $mensaje = "El empleado <b>$nombre</b> ya registró su entrada y salida de hoy.";
                $tipo_alerta = "alert-warning";
            }
        } else {
            // Registrar Entrada
            $sql_entrada = "INSERT INTO registros_asistencia (id_empleado, fecha, hora_ingreso) VALUES ('$codigo', '$fecha_hoy', '$hora_actual')";
            mysqli_query($conexion, $sql_entrada);
            $mensaje = "¡Bienvenido <b>$nombre</b>! Entrada registrada a las $hora_actual";
            $tipo_alerta = "alert-success";
        }
    } else {
        $mensaje = "Error: El código <b>$codigo</b> no pertenece a un empleado activo.";
        $tipo_alerta = "alert-danger";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Procesando...</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center vh-100">
    <div class="text-center">
        <div class="alert <?php echo $tipo_alerta; ?> shadow-sm p-4 mb-4" role="alert" style="font-size: 1.2rem;">
            <?php echo $mensaje; ?>
        </div>
        <a href="scan.php" class="btn btn-lg btn-primary shadow-sm">Volver al Escáner</a>
    </div>
</body>
</html>