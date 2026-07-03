<?php
include("conexion.php");
if(!isset($_SESSION['usuario'])) { header("Location: login.php"); exit(); }

date_default_timezone_set('America/El_Salvador');

// --- FILTROS (mismos parámetros que reportes.php) ---
$fecha_inicio = isset($_GET['fecha_inicio']) && $_GET['fecha_inicio'] !== '' ? $_GET['fecha_inicio'] : date('Y-m-01');
$fecha_fin    = isset($_GET['fecha_fin']) && $_GET['fecha_fin'] !== '' ? $_GET['fecha_fin'] : date('Y-m-d');
$id_empleado_filtro = isset($_GET['id_empleado']) ? trim($_GET['id_empleado']) : '';

if ($id_empleado_filtro !== '') {
    $sql = "SELECT r.id_empleado, e.nombre_completo, e.puesto, r.fecha, r.hora_ingreso, r.hora_salida
            FROM registros_asistencia r
            INNER JOIN empleados e ON e.id_empleado = r.id_empleado
            WHERE r.fecha BETWEEN ? AND ? AND r.id_empleado = ?
            ORDER BY r.fecha ASC, r.hora_ingreso ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "sss", $fecha_inicio, $fecha_fin, $id_empleado_filtro);
} else {
    $sql = "SELECT r.id_empleado, e.nombre_completo, e.puesto, r.fecha, r.hora_ingreso, r.hora_salida
            FROM registros_asistencia r
            INNER JOIN empleados e ON e.id_empleado = r.id_empleado
            WHERE r.fecha BETWEEN ? AND ?
            ORDER BY r.fecha ASC, r.hora_ingreso ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $fecha_inicio, $fecha_fin);
}
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

// --- CONSTRUIR FILAS Y CALCULAR HORAS TRABAJADAS ---
$filas = [];
while ($fila = mysqli_fetch_assoc($resultado)) {
    $horas_trabajadas = '';
    if (!empty($fila['hora_salida'])) {
        $inicio = strtotime($fila['fecha'] . ' ' . $fila['hora_ingreso']);
        $fin    = strtotime($fila['fecha'] . ' ' . $fila['hora_salida']);
        if ($fin > $inicio) {
            $horas_trabajadas = gmdate("H:i:s", $fin - $inicio);
        }
    }
    $fila['horas_trabajadas'] = $horas_trabajadas;
    $filas[] = $fila;
}

// --- NOMBRE DE ARCHIVO ---
$nombre_archivo = "asistencia_" . $fecha_inicio . "_a_" . $fecha_fin . ".xls";

// --- CABECERAS PARA DESCARGA COMO EXCEL ---
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$nombre_archivo\"");
header("Pragma: no-cache");
header("Expires: 0");

// BOM para que Excel interprete correctamente los acentos en UTF-8
echo "\xEF\xBB\xBF";
?>
<table border="1">
    <thead>
        <tr>
            <th colspan="7" style="font-size:14px;">Reporte de Asistencia (<?php echo htmlspecialchars($fecha_inicio); ?> a <?php echo htmlspecialchars($fecha_fin); ?>)</th>
        </tr>
        <tr style="background-color:#212529; color:#ffffff; font-weight:bold;">
            <th>Código</th>
            <th>Nombre</th>
            <th>Puesto</th>
            <th>Fecha</th>
            <th>Hora Entrada</th>
            <th>Hora Salida</th>
            <th>Horas Trabajadas</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($filas) === 0) { ?>
        <tr><td colspan="7">No hay registros para el rango seleccionado.</td></tr>
        <?php } ?>
        <?php foreach ($filas as $fila) { ?>
        <tr>
            <td><?php echo htmlspecialchars($fila['id_empleado']); ?></td>
            <td><?php echo htmlspecialchars($fila['nombre_completo']); ?></td>
            <td><?php echo htmlspecialchars($fila['puesto']); ?></td>
            <td><?php echo htmlspecialchars($fila['fecha']); ?></td>
            <td><?php echo htmlspecialchars($fila['hora_ingreso']); ?></td>
            <td><?php echo htmlspecialchars($fila['hora_salida'] ?? 'Pendiente'); ?></td>
            <td><?php echo htmlspecialchars($fila['horas_trabajadas'] !== '' ? $fila['horas_trabajadas'] : '—'); ?></td>
        </tr>
        <?php } ?>
    </tbody>
</table>
