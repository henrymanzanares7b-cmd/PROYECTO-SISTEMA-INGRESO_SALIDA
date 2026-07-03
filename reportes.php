<?php
include("conexion.php");
if(!isset($_SESSION['usuario'])) { header("Location: login.php"); exit(); }

date_default_timezone_set('America/El_Salvador');

// --- FILTROS (con valores por defecto: mes actual) ---
$fecha_inicio = isset($_GET['fecha_inicio']) && $_GET['fecha_inicio'] !== '' ? $_GET['fecha_inicio'] : date('Y-m-01');
$fecha_fin    = isset($_GET['fecha_fin']) && $_GET['fecha_fin'] !== '' ? $_GET['fecha_fin'] : date('Y-m-d');
$id_empleado_filtro = isset($_GET['id_empleado']) ? trim($_GET['id_empleado']) : '';

// --- CONSULTA PRINCIPAL (sentencia preparada) ---
if ($id_empleado_filtro !== '') {
    $sql = "SELECT r.id_empleado, e.nombre_completo, e.puesto, r.fecha, r.hora_ingreso, r.hora_salida
            FROM registros_asistencia r
            INNER JOIN empleados e ON e.id_empleado = r.id_empleado
            WHERE r.fecha BETWEEN ? AND ? AND r.id_empleado = ?
            ORDER BY r.fecha DESC, r.hora_ingreso DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "sss", $fecha_inicio, $fecha_fin, $id_empleado_filtro);
} else {
    $sql = "SELECT r.id_empleado, e.nombre_completo, e.puesto, r.fecha, r.hora_ingreso, r.hora_salida
            FROM registros_asistencia r
            INNER JOIN empleados e ON e.id_empleado = r.id_empleado
            WHERE r.fecha BETWEEN ? AND ?
            ORDER BY r.fecha DESC, r.hora_ingreso DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $fecha_inicio, $fecha_fin);
}
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

// --- ACUMULADORES PARA RESUMEN ---
$total_registros = 0;
$total_completos = 0;
$total_incompletos = 0;
$segundos_totales = 0;
$filas = [];

while ($fila = mysqli_fetch_assoc($resultado)) {
    $total_registros++;
    $horas_trabajadas = null;

    if (!empty($fila['hora_salida'])) {
        $total_completos++;
        $inicio = strtotime($fila['fecha'] . ' ' . $fila['hora_ingreso']);
        $fin    = strtotime($fila['fecha'] . ' ' . $fila['hora_salida']);
        if ($fin > $inicio) {
            $segundos = $fin - $inicio;
            $segundos_totales += $segundos;
            $horas_trabajadas = gmdate("H:i:s", $segundos);
        }
    } else {
        $total_incompletos++;
    }

    $fila['horas_trabajadas'] = $horas_trabajadas;
    $filas[] = $fila;
}

$horas_totales_texto = gmdate("H:i:s", $segundos_totales);

// --- LISTA DE EMPLEADOS PARA EL SELECT DEL FILTRO ---
$sql_empleados = "SELECT id_empleado, nombre_completo FROM empleados ORDER BY nombre_completo ASC";
$resultado_empleados = mysqli_query($conexion, $sql_empleados);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes de Asistencia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-upc-scan"></i> AsistenciaQR</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="empleados.php">Empleados</a></li>
                    <li class="nav-item"><a class="nav-link" href="scan.php">Escáner QR</a></li>
                    <li class="nav-item"><a class="nav-link active" href="reportes.php">Reportes</a></li>
                    <li class="nav-item"><a class="nav-link" href="permisos.php">Permisos</a></li>
                </ul>
                <div class="d-flex align-items-center">
                    <span class="text-white me-3">Hola, <b><?php echo htmlspecialchars($_SESSION['nombre_admin'] ?? $_SESSION['usuario']); ?></b></span>
                    <a href="logout.php" class="btn btn-danger btn-sm">Cerrar Sesión</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Reportes de Asistencia</h2>
            <form method="GET" action="exportar_excel.php" class="d-inline">
                <input type="hidden" name="fecha_inicio" value="<?php echo htmlspecialchars($fecha_inicio); ?>">
                <input type="hidden" name="fecha_fin" value="<?php echo htmlspecialchars($fecha_fin); ?>">
                <input type="hidden" name="id_empleado" value="<?php echo htmlspecialchars($id_empleado_filtro); ?>">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-file-earmark-excel"></i> Exportar a Excel
                </button>
            </form>
        </div>

        <!-- FILTROS -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="reportes.php" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Fecha inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="<?php echo htmlspecialchars($fecha_inicio); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha fin</label>
                        <input type="date" name="fecha_fin" class="form-control" value="<?php echo htmlspecialchars($fecha_fin); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Empleado</label>
                        <select name="id_empleado" class="form-select">
                            <option value="">-- Todos los empleados --</option>
                            <?php while ($emp = mysqli_fetch_assoc($resultado_empleados)) { ?>
                                <option value="<?php echo htmlspecialchars($emp['id_empleado']); ?>"
                                    <?php echo ($id_empleado_filtro === $emp['id_empleado']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['id_empleado'] . ' - ' . $emp['nombre_completo']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Filtrar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- TARJETAS RESUMEN -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-white shadow-sm" style="background-color:#0d6efd;">
                    <div class="card-body">
                        <div><i class="bi bi-clipboard-data"></i> Total Registros</div>
                        <div class="display-6 fw-bold"><?php echo $total_registros; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-white shadow-sm" style="background-color:#198754;">
                    <div class="card-body">
                        <div><i class="bi bi-check-circle"></i> Jornadas Completas</div>
                        <div class="display-6 fw-bold"><?php echo $total_completos; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-dark shadow-sm" style="background-color:#ffc107;">
                    <div class="card-body">
                        <div><i class="bi bi-exclamation-triangle"></i> Sin Salida Registrada</div>
                        <div class="display-6 fw-bold"><?php echo $total_incompletos; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-white shadow-sm" style="background-color:#6c757d;">
                    <div class="card-body">
                        <div><i class="bi bi-hourglass-split"></i> Horas Totales</div>
                        <div class="display-6 fw-bold"><?php echo $horas_totales_texto; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLA DE DETALLE -->
        <div class="card shadow-sm mb-5">
            <div class="card-body">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Puesto</th>
                            <th>Fecha</th>
                            <th>Entrada</th>
                            <th>Salida</th>
                            <th>Horas Trabajadas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($filas) === 0) { ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No hay registros para el rango seleccionado.</td></tr>
                        <?php } ?>
                        <?php foreach ($filas as $fila) { ?>
                            <tr>
                                <td><b><?php echo htmlspecialchars($fila['id_empleado']); ?></b></td>
                                <td><?php echo htmlspecialchars($fila['nombre_completo']); ?></td>
                                <td><?php echo htmlspecialchars($fila['puesto']); ?></td>
                                <td><?php echo htmlspecialchars($fila['fecha']); ?></td>
                                <td><?php echo htmlspecialchars($fila['hora_ingreso']); ?></td>
                                <td>
                                    <?php if ($fila['hora_salida']) {
                                        echo htmlspecialchars($fila['hora_salida']);
                                    } else { ?>
                                        <span class="badge bg-warning text-dark">Pendiente</span>
                                    <?php } ?>
                                </td>
                                <td><?php echo $fila['horas_trabajadas'] ? htmlspecialchars($fila['horas_trabajadas']) : '—'; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</body>
</html>
