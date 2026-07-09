<?php
session_start();
include("conexion.php");

if(!isset($_SESSION['usuario'])) { 
    header("Location: login.php"); 
    exit(); 
}

// 1. AUTO-MIGRACIÓN DE SEGURIDAD
// Verifica si existe el teléfono
$check_col_tel = mysqli_query($conexion, "SHOW COLUMNS FROM empleados LIKE 'telefono'");
if (mysqli_num_rows($check_col_tel) === 0) {
    mysqli_query($conexion, "ALTER TABLE empleados ADD COLUMN telefono VARCHAR(20) NULL DEFAULT ''");
}

// Verifica si existe el correo
$check_col_correo = mysqli_query($conexion, "SHOW COLUMNS FROM empleados LIKE 'correo'");
if (mysqli_num_rows($check_col_correo) === 0) {
    mysqli_query($conexion, "ALTER TABLE empleados ADD COLUMN correo VARCHAR(100) NULL DEFAULT '' AFTER telefono");
}

$tabla_permisos_existe = mysqli_query($conexion, "SHOW TABLES LIKE 'permisos_empleados'");

// 2. CONSULTA SQL
$sql_auditoria = "
    SELECT id_empleado, nombre_completo, telefono, correo 
    FROM empleados 
    WHERE estado = 'Activo' 
    AND NOT EXISTS (
        SELECT 1 FROM registros_asistencia 
        WHERE registros_asistencia.id_empleado = empleados.id_empleado 
        AND fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    )
";

if (mysqli_num_rows($tabla_permisos_existe) > 0) {
    $sql_auditoria .= "
        AND NOT EXISTS (
            SELECT 1 FROM permisos_empleados 
            WHERE permisos_empleados.id_empleado = empleados.id_empleado 
            AND fecha_fin >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        )
    ";
}

$sql_auditoria .= " ORDER BY nombre_completo ASC";
$resultado_auditoria = mysqli_query($conexion, $sql_auditoria);

if (!$resultado_auditoria) {
    die("Error en la consulta SQL: " . mysqli_error($conexion));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sist.Control - Auditoría</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f6f9; }
        .navbar-custom { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); }
        .table-custom th { background-color: #f8f9fa; color: #495057; border-bottom: 2px solid #dee2e6; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px;}
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-upc-scan me-2"></i>Sist.Control</a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="empleados.php">Personal</a></li>
                    <li class="nav-item"><a class="nav-link" href="scan.php">Escáner QR</a></li>
                    <li class="nav-item"><a class="nav-link" href="reportes.php">Reportes</a></li>
                    <li class="nav-item"><a class="nav-link active text-warning fw-semibold" href="auditoria_inasistencias.php">Auditoría</a></li>
                </ul>
                <div class="d-flex align-items-center">
                    <span class="text-white me-3">Hola, <b><?php echo htmlspecialchars($_SESSION['nombre_admin'] ?? $_SESSION['usuario']); ?></b></span>
                    <a href="logout.php" class="btn btn-danger btn-sm">Cerrar Sesión</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mb-5">
        <div class="row mb-4 align-items-center">
            <div class="col">
                <h2 class="fw-bold text-dark m-0">Auditoría de Inasistencias</h2>
                <p class="text-muted m-0">Colaboradores sin registro ni permiso en los últimos 7 días</p>
            </div>
        </div>

        <div class="card shadow-sm border-0 rounded-4 bg-white">
            <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0 rounded-start-pill"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="inputBuscar" class="form-control bg-light border-0 rounded-end-pill ps-2" placeholder="Buscar colaborador por nombre o código...">
                </div>
            </div>

            <div class="card-body p-0 overflow-hidden rounded-4 mt-2">
                <div class="table-responsive">
                    <table class="table table-hover table-custom align-middle mb-0" id="tablaAuditoria">
                        <thead>
                            <tr>
                                <th class="ps-4">Código</th>
                                <th>Colaborador</th>
                                <th>Contacto</th>
                                <th class="text-end pe-4">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (mysqli_num_rows($resultado_auditoria) > 0) {
                                while ($fila = mysqli_fetch_assoc($resultado_auditoria)) {
                                    $nombre = htmlspecialchars($fila['nombre_completo']);
                                    
                                    // --- LÓGICA DE WHATSAPP ---
                                    $telefono_limpio = '';
                                    if (!empty($fila['telefono'])) {
                                        $telefono_limpio = preg_replace('/[^0-9]/', '', $fila['telefono']); 
                                    }
                                    $mensaje_wsp = rawurlencode("Hola $nombre, notamos que no has registrado asistencia en MARCAJE CMD durante la última semana. ¿Todo bien?");
                                    
                                    if ($telefono_limpio !== '') {
                                        $btn_wsp = "<a href='https://wa.me/503{$telefono_limpio}?text={$mensaje_wsp}' target='_blank' class='btn btn-success btn-sm rounded-pill px-3 shadow-sm'>
                                                        <i class='bi bi-whatsapp me-1'></i>
                                                    </a>";
                                    } else {
                                        $btn_wsp = "";
                                    }

                                    // --- LÓGICA DE CORREO ELECTRÓNICO ---
                                    $correo_limpio = !empty($fila['correo']) ? trim($fila['correo']) : '';
                                    $asunto_correo = rawurlencode("Aviso de Inasistencia - MARCAJE CMD");
                                    $cuerpo_correo = rawurlencode("Hola $nombre,\n\nNotamos que no has registrado asistencia en MARCAJE CMD durante la última semana. ¿Todo bien?\n\nSaludos.");
                                    
                                    if ($correo_limpio !== '') {
                                        $btn_correo = "<a href='mailto:{$correo_limpio}?subject={$asunto_correo}&body={$cuerpo_correo}' class='btn btn-primary btn-sm rounded-pill px-3 shadow-sm ms-1'>
                                                        <i class='bi bi-envelope me-1'></i>
                                                       </a>";
                                    } else {
                                        $btn_correo = "";
                                    }
                                    
                                    // --- ETIQUETA SI NO TIENE NINGÚN CONTACTO ---
                                    if($telefono_limpio === '' && $correo_limpio === '') {
                                        $botones_accion = "<span class='badge bg-light text-dark border'>Sin contacto</span>";
                                    } else {
                                        $botones_accion = $btn_wsp . $btn_correo;
                                    }

                                    echo "<tr>";
                                    echo "<td class='ps-4 fw-bold text-secondary'>" . htmlspecialchars($fila['id_empleado']) . "</td>";
                                    echo "<td>
                                            <div class='d-flex align-items-center'>
                                                <div class='bg-light rounded-circle p-2 me-3 text-secondary'><i class='bi bi-person-exclamation'></i></div>
                                                <span class='fw-bold text-dark d-block'>{$nombre}</span>
                                            </div>
                                          </td>";
                                    
                                    // Columna que muestra los datos de contacto registrados
                                    echo "<td>";
                                    if($telefono_limpio !== '') echo "<small class='d-block'><i class='bi bi-telephone text-success me-1'></i> " . htmlspecialchars($fila['telefono']) . "</small>";
                                    if($correo_limpio !== '') echo "<small class='d-block'><i class='bi bi-envelope text-primary me-1'></i> " . htmlspecialchars($fila['correo']) . "</small>";
                                    if($telefono_limpio === '' && $correo_limpio === '') echo "<small class='text-muted'>--</small>";
                                    echo "</td>";
                                    
                                    echo "<td class='text-end pe-4'>{$botones_accion}</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr id='filaNoHay'><td colspan='4' class='text-center py-5 text-muted'><i class='bi bi-check-circle fs-2 d-block mb-2 text-success'></i>Todos los colaboradores activos tienen registros o permisos al día.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('inputBuscar').addEventListener('keyup', function() {
            let filtro = this.value.toLowerCase();
            let filas = document.querySelectorAll('#tablaAuditoria tbody tr');
            
            filas.forEach(fila => {
                // Ignorar la fila de "Todo está bien" si aparece
                if(fila.id === 'filaNoHay') return;

                // Capturamos el texto de Código y Colaborador
                let codigo = fila.cells[0].textContent.toLowerCase();
                let nombre = fila.cells[1].textContent.toLowerCase();
                
                if(codigo.includes(filtro) || nombre.includes(filtro)) {
                    fila.style.display = '';
                } else {
                    fila.style.display = 'none';
                }
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>