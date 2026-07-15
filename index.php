<?php
include("conexion.php");
if(!isset($_SESSION['usuario'])) { header("Location: login.php"); exit(); }

date_default_timezone_set('America/El_Salvador');
$fecha_hoy = date('Y-m-d');
$fecha_display = date('d/m/Y');

// --- 1. MÉTRICAS SUPERIORES ---

// Total de Empleados Activos
$query_emp = mysqli_query($conexion, "SELECT COUNT(*) as total FROM empleados WHERE estado = 'Activo'");
$total_activos = mysqli_fetch_assoc($query_emp)['total'] ?? 0;

// Total de Entradas Hoy (Cualquier registro creado hoy)
$query_ent = mysqli_query($conexion, "SELECT COUNT(*) as total FROM registros_asistencia WHERE fecha = '$fecha_hoy'");
$entradas_hoy = mysqli_fetch_assoc($query_ent)['total'] ?? 0;

// Total de Salidas Hoy (Registros de hoy que ya tienen hora de salida)
$query_sal = mysqli_query($conexion, "SELECT COUNT(*) as total FROM registros_asistencia WHERE fecha = '$fecha_hoy' AND hora_salida IS NOT NULL");
$salidas_hoy = mysqli_fetch_assoc($query_sal)['total'] ?? 0;

// --- 2. RESUMEN DE HORARIOS ---
// Agrupamos y contamos cuántos empleados activos están asignados a cada turno en la base de datos
$query_horarios = "SELECT h.nombre_horario, h.hora_entrada, h.hora_salida, COUNT(e.id_empleado) as total_empleados
                   FROM horarios h
                   LEFT JOIN empleados e ON h.id_horario = e.id_horario AND e.estado = 'Activo'
                   GROUP BY h.id_horario
                   ORDER BY h.hora_entrada ASC";
$resultado_horarios = mysqli_query($conexion, $query_horarios);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - AsistenciaQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .metric-card {
            border: none;
            border-radius: 8px;
            color: white;
            padding: 20px;
        }
        .metric-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 5px; }
        .metric-value { font-size: 3.5rem; font-weight: 700; line-height: 1; }
        
        .bg-blue { background-color: #0d6efd; }
        .bg-green { background-color: #198754; }
        .bg-yellow { background-color: #ffc107; color: #272829 !important; }
    </style>
</head>
<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-upc-scan"></i> Sist.Control</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link active" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="empleados.php">Empleados</a></li>
                    <li class="nav-item"><a class="nav-link" href="scan.php">Escáner QR</a></li>
                    <li class="nav-item"><a class="nav-link" href="reportes.php">Análitica</a></li>
                    <li class="nav-item"><a class="nav-link" href="permisos.php">Permisos</a></li>
                    <li class="nav-item"><a class="nav-link text-warning" href="auditoria_inasistencias.php">Auditoría</a></li>
                </ul>
                <div class="d-flex align-items-center">
                    <span class="text-white me-3">Hola, <b><?php echo htmlspecialchars($_SESSION['nombre_admin'] ?? $_SESSION['usuario']); ?></b></span>
                    <a href="logout.php" class="btn btn-danger btn-sm">Cerrar Sesión</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mb-5">
        
        <h2 class="mb-4">Resumen de Hoy (<?php echo $fecha_display; ?>)</h2>

        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="metric-card bg-blue shadow-sm">
                    <div class="metric-title"><i class="bi bi-people-fill"></i> Empleados Activos</div>
                    <div class="metric-value"><?php echo $total_activos; ?></div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="metric-card bg-green shadow-sm">
                    <div class="metric-title"><i class="bi bi-box-arrow-in-right"></i> Entradas Hoy</div>
                    <div class="metric-value"><?php echo $entradas_hoy; ?></div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="metric-card bg-yellow shadow-sm">
                    <div class="metric-title"><i class="bi bi-box-arrow-left"></i> Salidas Hoy</div>
                    <div class="metric-value"><?php echo $salidas_hoy; ?></div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-center gap-3 mb-5">
            <a href="scan.php" class="btn btn-success btn-lg px-4 shadow-sm">
                <i class="bi bi-camera"></i> Abrir Lector QR
            </a>
            <a href="empleados.php" class="btn btn-secondary btn-lg px-4 shadow-sm">
                <i class="bi bi-people"></i> Gestionar Personal
            </a>
            <a href="reportes.php" class="btn btn-primary btn-lg px-4 shadow-sm">
                <i class="bi bi-bar-chart-line"></i> Ver Reportes
            </a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white fw-bold py-3">
                <i class="bi bi-clock-history"></i> Resumen de Horarios y Turnos
            </div>
            <div class="card-body p-0">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Turno</th>
                            <th>Hora Entrada</th>
                            <th>Hora Salida</th>
                            <th class="text-center">Personal Asignado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (mysqli_num_rows($resultado_horarios) > 0) {
                            while ($horario = mysqli_fetch_assoc($resultado_horarios)) { 
                                $hora_in = date("h:i A", strtotime($horario['hora_entrada']));
                                $hora_out = date("h:i A", strtotime($horario['hora_salida']));
                        ?>
                                <tr>
                                    <td class="ps-4 fw-semibold text-primary">
                                        <?php echo htmlspecialchars($horario['nombre_horario']); ?>
                                    </td>
                                    <td><?php echo $hora_in; ?></td>
                                    <td><?php echo $hora_out; ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary rounded-pill fs-6 px-3">
                                            <?php echo $horario['total_empleados']; ?> empleados
                                        </span>
                                    </td>
                                </tr>
                        <?php 
                            }
                        } else {
                            echo "<tr><td colspan='4' class='text-center py-4 text-muted'>No hay horarios registrados.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>