<?php
// permisos.php
include("conexion.php");
date_default_timezone_set('America/El_Salvador');

if(!isset($_SESSION['usuario'])) { 
    // header("Location: login.php"); // Descomenta si usas control de sesiones
}

$mensaje = '';

// --- PROCESAR FORMULARIO DE REGISTRO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_permiso'])) {
    $id_empleado = trim($_POST['id_empleado']);
    $tipo_permiso = trim($_POST['tipo_permiso']);
    $modalidad = trim($_POST['modalidad']);
    $motivo = trim($_POST['motivo']);
    $descripcion = trim($_POST['descripcion']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    
    // Si es día completo, enviamos NULL a las horas
    $hora_inicio = !empty($_POST['hora_inicio']) ? $_POST['hora_inicio'] : null;
    $hora_fin = !empty($_POST['hora_fin']) ? $_POST['hora_fin'] : null;
    
    // Todo nuevo registro entra como Pendiente por defecto
    $estado = 'Pendiente';
    $fecha_solicitud = date('Y-m-d H:i:s');

    $sql_insert = "INSERT INTO solicitudes_permisos (id_empleado, tipo_permiso, modalidad, motivo, descripcion, fecha_inicio, fecha_fin, hora_inicio, hora_fin, estado, fecha_solicitud) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($conexion, $sql_insert);
    mysqli_stmt_bind_param($stmt, "sssssssssss", $id_empleado, $tipo_permiso, $modalidad, $motivo, $descripcion, $fecha_inicio, $fecha_fin, $hora_inicio, $hora_fin, $estado, $fecha_solicitud);
    
    if (mysqli_stmt_execute($stmt)) {
        $mensaje = "<div class='alert alert-success alert-dismissible fade show'>Solicitud registrada correctamente y marcada como Pendiente.<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } else {
        $mensaje = "<div class='alert alert-danger alert-dismissible fade show'>Error al registrar: " . mysqli_error($conexion) . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
    mysqli_stmt_close($stmt);
}

// --- OBTENER EMPLEADOS PARA EL SELECT ---
$sql_empleados = "SELECT id_empleado, nombre_completo FROM empleados WHERE estado = 'Activo' ORDER BY nombre_completo ASC";
$res_empleados = mysqli_query($conexion, $sql_empleados);

// --- OBTENER SOLICITUDES PARA LA TABLA (INNER JOIN) ---
$sql_solicitudes = "SELECT sp.id_solicitud, e.nombre_completo, sp.tipo_permiso, sp.modalidad, sp.motivo, sp.descripcion, 
                           sp.fecha_inicio, sp.fecha_fin, sp.hora_inicio, sp.hora_fin, sp.estado, sp.fecha_solicitud 
                    FROM solicitudes_permisos sp 
                    INNER JOIN empleados e ON sp.id_empleado = e.id_empleado 
                    ORDER BY sp.fecha_solicitud DESC";
$res_solicitudes = mysqli_query($conexion, $sql_solicitudes);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sist.Control - Gestión de Permisos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Diseño idéntico a tu captura */
        .navbar-custom {
            background-color: #0d6efd; /* Azul estándar igual a la imagen */
        }
        .navbar-custom .navbar-brand {
            color: #ffffff;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 8px; /* Espacio entre el icono y el texto */
        }
        .navbar-custom .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.75); /* Letras ligeramente transparentes */
            font-size: 1rem;
            margin-right: 10px;
        }
        .navbar-custom .navbar-nav .nav-link:hover {
            color: #ffffff;
        }
        /* Pestaña activa (blanca y en negrita) */
        .navbar-custom .navbar-nav .nav-link.active-tab {
            color: #ffffff !important; 
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-custom mb-4 py-3 shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="#">
            <i class="bi bi-upc-scan"></i> Sist.Control
        </a>
        
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#menuNavegacion">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="menuNavegacion">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-3">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="empleados.php">Empleados</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="scan.php">Escáner QR</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="analitica.php">Análitica</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active-tab" href="permisos.php">Permisos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="auditoria.php">Auditoría</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-dark fw-bold mb-0">Gestión de Solicitudes de Permisos</h2>
    </div>
    
    <?= $mensaje ?>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Nueva Solicitud</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="permisos.php">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Empleado</label>
                            <select name="id_empleado" class="form-select" required>
                                <option value="">Seleccione un empleado...</option>
                                <?php while ($emp = mysqli_fetch_assoc($res_empleados)): ?>
                                    <option value="<?= htmlspecialchars($emp['id_empleado']) ?>">
                                        <?= htmlspecialchars($emp['nombre_completo']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <label class="form-label fw-bold">Tipo de Permiso</label>
                                <select name="tipo_permiso" class="form-select" required>
                                    <option value="">Seleccione...</option>
                                    <option value="Médico">Médico</option>
                                    <option value="Personal">Personal</option>
                                    <option value="Vacaciones">Vacaciones</option>
                                    <option value="Luto">Luto</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>
                            <div class="col">
                                <label class="form-label fw-bold">Modalidad</label>
                                <select name="modalidad" id="modalidadSelect" class="form-select" required>
                                    <option value="Día completo">Día completo</option>
                                    <option value="Por horas">Por horas</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Motivo Breve</label>
                            <input type="text" name="motivo" class="form-control" required placeholder="Ej: Cita médica">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Descripción / Detalles</label>
                            <textarea name="descripcion" class="form-control" rows="2"></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col">
                                <label class="form-label fw-bold">Fecha Inicio</label>
                                <input type="date" name="fecha_inicio" class="form-control" required>
                            </div>
                            <div class="col">
                                <label class="form-label fw-bold">Fecha Fin</label>
                                <input type="date" name="fecha_fin" class="form-control" required>
                            </div>
                        </div>

                        <div class="row mb-4" id="contenedorHoras" style="display: none;">
                            <div class="col">
                                <label class="form-label fw-bold text-primary">Hora Salida</label>
                                <input type="time" name="hora_inicio" id="hora_inicio" class="form-control">
                            </div>
                            <div class="col">
                                <label class="form-label fw-bold text-primary">Hora Regreso</label>
                                <input type="time" name="hora_fin" id="hora_fin" class="form-control">
                            </div>
                        </div>

                        <button type="submit" name="registrar_permiso" class="btn btn-primary w-100 fw-bold">Registrar Solicitud</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Historial de Solicitudes</h5>
                    <a href="exportar_permisos.php" class="btn btn-success btn-sm">Exportar Excel</a>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Empleado</th>
                                <th>Tipo</th>
                                <th>Fechas / Horas</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($res_solicitudes) > 0): ?>
                                <?php while ($sol = mysqli_fetch_assoc($res_solicitudes)): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($sol['nombre_completo']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($sol['motivo']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($sol['tipo_permiso']) ?></td>
                                        <td>
                                            <small>
                                                <span class="badge bg-secondary mb-1"><?= htmlspecialchars($sol['modalidad'] ?? 'Día completo') ?></span><br>
                                                <strong>Inicia:</strong> <?= date("d/m/Y", strtotime($sol['fecha_inicio'])) ?>
                                                <?php if ($sol['modalidad'] === 'Por horas' && !empty($sol['hora_inicio'])): ?>
                                                    <span class="text-primary"> a las <?= date("h:i A", strtotime($sol['hora_inicio'])) ?></span>
                                                <?php endif; ?>
                                                <br>
                                                <strong>Termina:</strong> <?= date("d/m/Y", strtotime($sol['fecha_fin'])) ?>
                                                <?php if ($sol['modalidad'] === 'Por horas' && !empty($sol['hora_fin'])): ?>
                                                    <span class="text-primary"> a las <?= date("h:i A", strtotime($sol['hora_fin'])) ?></span>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($sol['estado'] === 'Pendiente'): ?>
                                                <span class="badge bg-warning text-dark">Pendiente</span>
                                            <?php elseif ($sol['estado'] === 'Aprobado'): ?>
                                                <span class="badge bg-success">Aprobado</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Rechazado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group shadow-sm" role="group">
                                                <a href="procesar_estado_permiso.php?id=<?= $sol['id_solicitud'] ?>&accion=aprobar" 
                                                   class="btn btn-sm <?= $sol['estado'] === 'Aprobado' ? 'btn-success text-white' : 'btn-outline-success' ?>">
                                                   Aprobar
                                                </a>
                                                <a href="procesar_estado_permiso.php?id=<?= $sol['id_solicitud'] ?>&accion=rechazar" 
                                                   class="btn btn-sm <?= $sol['estado'] === 'Rechazado' ? 'btn-danger text-white' : 'btn-outline-danger' ?>">
                                                   Rechazar
                                                </a>
                                                <a href="procesar_estado_permiso.php?id=<?= $sol['id_solicitud'] ?>&accion=eliminar" 
                                                   class="btn btn-sm btn-outline-secondary"
                                                   onclick="return confirm('¿Estás seguro de que deseas eliminar esta solicitud permanentemente?');">
                                                   Eliminar
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-3">No hay solicitudes registradas.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const modalidadSelect = document.getElementById('modalidadSelect');
        const contenedorHoras = document.getElementById('contenedorHoras');
        const inputHoraInicio = document.getElementById('hora_inicio');
        const inputHoraFin = document.getElementById('hora_fin');

        function toggleHoras() {
            if (modalidadSelect.value === 'Por horas') {
                contenedorHoras.style.display = 'flex'; // Muestra las casillas
                inputHoraInicio.setAttribute('required', 'required'); // Hace obligatorio llenar la hora
                inputHoraFin.setAttribute('required', 'required');
            } else {
                contenedorHoras.style.display = 'none'; // Oculta las casillas
                inputHoraInicio.removeAttribute('required'); // Quita la obligatoriedad
                inputHoraFin.removeAttribute('required');
                inputHoraInicio.value = ''; // Limpia el campo por seguridad
                inputHoraFin.value = '';
            }
        }

        // Ejecutar al cambiar la opción
        modalidadSelect.addEventListener('change', toggleHoras);
        
        // Ejecutar al cargar la página
        toggleHoras();
    });
</script>

</body>
</html>