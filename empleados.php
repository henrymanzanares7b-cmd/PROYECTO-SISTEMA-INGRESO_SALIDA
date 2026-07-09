<?php
session_start();
include("conexion.php");

require_once 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Validación de sesión
if(!isset($_SESSION['usuario'])) { 
    header("Location: login.php"); 
    exit(); 
}

// ==========================================
// 1. LÓGICA PARA AGREGAR NUEVO EMPLEADO
// ==========================================
if (isset($_POST['agregar'])) {
    $id_empleado = mysqli_real_escape_string($conexion, trim($_POST['id_empleado']));
    $nombre_completo = mysqli_real_escape_string($conexion, trim($_POST['nombre_completo']));
    $puesto = mysqli_real_escape_string($conexion, trim($_POST['puesto']));

    $check_sql = "SELECT id_empleado FROM empleados WHERE id_empleado = '$id_empleado'";
    $check_result = mysqli_query($conexion, $check_sql);

    if (mysqli_num_rows($check_result) > 0) {
        $mensaje_error = "El código de empleado '$id_empleado' ya existe.";
    } else {
        $insert_sql = "INSERT INTO empleados (id_empleado, nombre_completo, puesto) VALUES ('$id_empleado', '$nombre_completo', '$puesto')";
        if (mysqli_query($conexion, $insert_sql)) {
            header("Location: empleados.php?mensaje=agregado");
            exit();
        } else {
            $mensaje_error = "Error al registrar: " . mysqli_error($conexion);
        }
    }
}

// ==========================================
// 2. LÓGICA PARA ELIMINAR EMPLEADO
// ==========================================
if (isset($_GET['eliminar'])) {
    $id_eliminar = mysqli_real_escape_string($conexion, $_GET['eliminar']);
    mysqli_query($conexion, "DELETE FROM registros_asistencia WHERE id_empleado = '$id_eliminar'");
    if (mysqli_query($conexion, "DELETE FROM empleados WHERE id_empleado = '$id_eliminar'")) {
        header("Location: empleados.php?mensaje=eliminado");
        exit();
    }
}

// ==========================================
// LÓGICA PARA EXPORTAR REPORTE GENERAL (.XLSX)
// ==========================================
if (isset($_GET['exportar_excel']) && $_GET['exportar_excel'] == '1') {
    if (ob_get_length()) ob_clean();
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Reporte General');

    $sheet->setCellValue('A1', 'MARCAJE CMD - REPORTE GENERAL');
    $sheet->mergeCells('A1:F1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->setCellValue('A2', 'Reporte generado el: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:F2');

    $cabeceras = ['Colaborador', 'Fecha', 'Ingreso', 'Salida', 'Horas Calculadas', 'Diagnóstico de Estado'];
    $letra = 'A';
    foreach ($cabeceras as $cabecera) {
        $sheet->setCellValue($letra . '4', $cabecera);
        $letra++;
    }
    $sheet->getStyle('A4:F4')->applyFromArray(['font'=>['bold'=>true,'color'=>['argb'=>'FFFFFFFF']],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FF0d6efd']]]);

    $sql_export = "SELECT e.nombre_completo, r.fecha, r.hora_ingreso, r.hora_salida, r.estado_marca,
                ROUND(TIME_TO_SEC(TIMEDIFF(r.hora_salida, r.hora_ingreso))/3600, 2) AS horas_trabajadas
                FROM registros_asistencia r INNER JOIN empleados e ON r.id_empleado = e.id_empleado
                WHERE r.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ORDER BY r.fecha DESC";
    $resultado_export = mysqli_query($conexion, $sql_export);

    $filaExcel = 5;
    while ($fila = mysqli_fetch_assoc($resultado_export)) {
        $sheet->setCellValue('A'.$filaExcel, $fila['nombre_completo']);
        $sheet->setCellValue('B'.$filaExcel, date("d/m/Y", strtotime($fila['fecha'])));
        $sheet->setCellValue('C'.$filaExcel, $fila['hora_ingreso'] ? date("h:i A", strtotime($fila['hora_ingreso'])) : '--:--');
        $sheet->setCellValue('D'.$filaExcel, $fila['hora_salida'] ? date("h:i A", strtotime($fila['hora_salida'])) : '--:--');
        $sheet->setCellValue('E'.$filaExcel, $fila['horas_trabajadas'] ?? '0');
        $sheet->setCellValue('F'.$filaExcel, $fila['estado_marca']);
        $filaExcel++;
    }
    foreach (range('A', 'F') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="Reporte_Asistencias_SistControl_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

// ==========================================
// LÓGICA PARA EXPORTAR HISTORIAL INDIVIDUAL (.XLSX)
// ==========================================
if (isset($_GET['exportar_historial_excel']) && !empty($_GET['exportar_historial_excel'])) {
    if (ob_get_length()) ob_clean();
    $id_empleado_export = mysqli_real_escape_string($conexion, $_GET['exportar_historial_excel']);
    $query_nom = mysqli_query($conexion, "SELECT nombre_completo FROM empleados WHERE id_empleado = '$id_empleado_export'");
    $nombre_emp = (mysqli_num_rows($query_nom) > 0) ? mysqli_fetch_assoc($query_nom)['nombre_completo'] : 'Empleado';
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Historial Individual');

    $sheet->setCellValue('A1', 'MARCAJE CMD - HISTORIAL DEL COLABORADOR');
    $sheet->mergeCells('A1:D1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValue('A2', 'Colaborador: ' . $nombre_emp);
    $sheet->mergeCells('A2:D2');
    $sheet->setCellValue('A3', 'Reporte generado el: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A3:D3');

    $cabeceras = ['Fecha', 'Entrada', 'Salida', 'Estado del Marcaje'];
    $letra = 'A';
    foreach ($cabeceras as $cabecera) {
        $sheet->setCellValue($letra . '5', $cabecera);
        $letra++;
    }
    $sheet->getStyle('A5:D5')->applyFromArray(['font'=>['bold'=>true,'color'=>['argb'=>'FFFFFFFF']],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['argb'=>'FF198754']]]); // Verde para distinguir el individual

    $sql_export_historial = "SELECT fecha, hora_ingreso, hora_salida, estado_marca FROM registros_asistencia WHERE id_empleado = '$id_empleado_export' ORDER BY fecha DESC";
    $resultado_export_hist = mysqli_query($conexion, $sql_export_historial);

    $filaExcel = 6;
    while ($fila = mysqli_fetch_assoc($resultado_export_hist)) {
        $sheet->setCellValue('A'.$filaExcel, date("d/m/Y", strtotime($fila['fecha'])));
        $sheet->setCellValue('B'.$filaExcel, $fila['hora_ingreso'] ? date("h:i A", strtotime($fila['hora_ingreso'])) : '--:--');
        $sheet->setCellValue('C'.$filaExcel, $fila['hora_salida'] ? date("h:i A", strtotime($fila['hora_salida'])) : '--:--');
        $sheet->setCellValue('D'.$filaExcel, $fila['estado_marca']);
        $filaExcel++;
    }
    foreach (range('A', 'D') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }

    $nombre_archivo = 'Historial_' . str_replace(' ', '_', $nombre_emp) . '_' . date('Y-m-d') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sist.Control - Personal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f6f9; }
        .navbar-custom { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); }
        .table-custom th { background-color: #f8f9fa; color: #495057; border-bottom: 2px solid #dee2e6; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px;}
        .card { transition: all 0.3s ease; }
        .card:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-4 shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-upc-scan me-2"></i>Sist.Control</a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active fw-semibold" href="empleados.php">Personal</a></li>
                    <li class="nav-item"><a class="nav-link" href="scan.php">Escáner QR</a></li>
                    <li class="nav-item"><a class="nav-link" href="reportes.php">Reportes</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mb-5">
        <div class="row mb-4 align-items-center">
            <div class="col">
                <h2 class="fw-bold text-dark m-0">Directorio de Personal</h2>
                <p class="text-muted m-0">Gestión de empleados y asignación de horarios</p>
            </div>
        </div>

        <?php if(isset($_GET['mensaje']) && $_GET['mensaje'] == 'agregado'): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><strong>¡Éxito!</strong> Colaborador registrado correctamente.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['mensaje']) && $_GET['mensaje'] == 'eliminado'): ?>
            <div class="alert alert-warning alert-dismissible fade show shadow-sm border-0" role="alert">
                <i class="bi bi-trash3-fill me-2"></i>Colaborador eliminado del sistema.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if(isset($mensaje_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Error:</strong> <?php echo $mensaje_error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card shadow-sm rounded-4 border-0">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h5 class="fw-bold text-primary"><i class="bi bi-person-plus-fill me-2"></i>Alta de Empleado</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="empleados.php">
                            <div class="form-floating mb-3">
                                <input type="text" name="id_empleado" class="form-control rounded-3" id="floatingId" placeholder="EMP-004" required>
                                <label for="floatingId">Código de Identificación</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="text" name="nombre_completo" class="form-control rounded-3" id="floatingName" placeholder="Juan Perez" required>
                                <label for="floatingName">Nombre Completo</label>
                            </div>
                            <div class="form-floating mb-4">
                                <input type="text" name="puesto" class="form-control rounded-3" id="floatingPosition" placeholder="Desarrollador" required>
                                <label for="floatingPosition">Puesto / Cargo</label>
                            </div>
                            <div class="mb-3">
                                <select class="form-select form-select-custom" name="estado">
                                    <option value="Activo" selected>Estado: Activo</option>
                                    <option value="Inactivo">Estado: Inactivo</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <select class="form-select form-select-custom" name="nivel" required>
                                    <option value="" disabled>Seleccione un nivel...</option>
                                    <?php 
                                    // Cargar niveles dinámicamente si la consulta funcionó, de lo contrario usar opciones por defecto
                                    if(isset($niveles_para_select) && mysqli_num_rows($niveles_para_select) > 0) {
                                        while ($n = mysqli_fetch_assoc($niveles_para_select)) {
                                            $selected = ($n['nivel'] == 'Operativo') ? 'selected' : '';
                                            echo "<option value='".htmlspecialchars($n['nivel'])."' $selected>".htmlspecialchars($n['nivel'])."</option>";
                                        }
                                    } else {
                                        echo '<option value="Operativo" selected>Operativo (Predeterminado)</option>';
                                        echo '<option value="Supervisor">Supervisor</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <input type="number" class="form-control" name="id_horario" placeholder="ID de Horario (Opcional)">
                            </div>

                            <div class="mb-3">
                                <input type="tel" class="form-control" name="telefono" placeholder="Teléfono (Opcional)">
                            </div>

                            <div class="mb-4">
                                <input type="email" class="form-control" name="correo" placeholder="Correo Electrónico (Opcional)">
                            </div>
                            <button type="submit" name="agregar" class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm py-2"><i class="bi bi-save me-2"></i>Registrar</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card shadow-sm rounded-4 border-0 bg-white">
                    <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0 rounded-start-pill"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="inputBuscar" class="form-control bg-light border-0 rounded-end-pill ps-2" placeholder="Buscar empleado por nombre, código o puesto...">
                        </div>
                    </div>
                    
                    <div class="card-body p-0 overflow-hidden rounded-4">
                        <div class="table-responsive">
                            <table class="table table-hover table-custom align-middle mb-0" id="tablaEmpleados">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Código</th>
                                        <th>Colaborador</th>
                                        <th>Puesto</th>
                                        <th>Horas Semanales</th>
                                        <th>Estado</th>
                                        <th class="text-end pe-4">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // MODIFICADO: LEFT JOIN para calcular horas de la semana en curso
                                    $sql_select = "SELECT e.id_empleado, e.nombre_completo, e.puesto,
                                                          COALESCE(ROUND(SUM(TIME_TO_SEC(TIMEDIFF(r.hora_salida, r.hora_ingreso)))/3600, 2), 0) AS horas_semanales
                                                   FROM empleados e
                                                   LEFT JOIN registros_asistencia r 
                                                          ON e.id_empleado = r.id_empleado 
                                                          AND YEARWEEK(r.fecha, 1) = YEARWEEK(CURDATE(), 1)
                                                          AND r.hora_salida IS NOT NULL
                                                   GROUP BY e.id_empleado, e.nombre_completo, e.puesto
                                                   ORDER BY e.id_empleado ASC";
                                                   
                                    $resultado = mysqli_query($conexion, $sql_select);
                                    if(mysqli_num_rows($resultado) > 0) {
                                        while($fila = mysqli_fetch_assoc($resultado)) {
                                            echo "<tr>";
                                            echo "<td class='ps-4 fw-bold text-secondary'>" . htmlspecialchars($fila['id_empleado']) . "</td>";
                                            echo "<td>
                                                    <div class='d-flex align-items-center'>
                                                        <div class='bg-light rounded-circle p-2 me-3 text-primary'><i class='bi bi-person-badge'></i></div>
                                                        <div>
                                                            <a href='empleados.php?ver_historial=" . urlencode($fila['id_empleado']) . "' class='text-decoration-none fw-bold text-dark d-block'>" . htmlspecialchars($fila['nombre_completo']) . "</a>
                                                            <small class='text-muted'>Ver historial <i class='bi bi-arrow-right-short'></i></small>
                                                        </div>
                                                    </div>
                                                </td>";
                                            echo "<td><span class='badge bg-body-secondary text-dark border'>" . htmlspecialchars($fila['puesto']) . "</span></td>";
                                            echo "<td><span class='badge bg-info text-dark rounded-pill shadow-sm px-3'>" . htmlspecialchars($fila['horas_semanales']) . " hrs</span></td>";
                                            echo "<td><span class='badge rounded-pill text-bg-success shadow-sm'>Activo</span></td>";
                                            echo "<td class='text-end pe-4'>
                                                    <a href='empleados.php?eliminar=" . urlencode($fila['id_empleado']) . "' class='btn btn-outline-danger btn-sm rounded-circle' onclick='return confirm(\"¿Estás seguro de borrar a " . htmlspecialchars($fila['nombre_completo']) . "? Esto borrará también su historial de asistencia.\")'><i class='bi bi-trash3'></i></a>
                                                </td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr id='filaNoHay'><td colspan='6' class='text-center py-4 text-muted'>No hay empleados registrados. Usa el formulario de la izquierda para agregar uno.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if(isset($_GET['ver_historial'])): 
        $id_historial = mysqli_real_escape_string($conexion, $_GET['ver_historial']);
        $query_nom = mysqli_query($conexion, "SELECT nombre_completo FROM empleados WHERE id_empleado = '$id_historial'");
        $nombre_emp = (mysqli_num_rows($query_nom) > 0) ? mysqli_fetch_assoc($query_nom)['nombre_completo'] : 'Empleado Desconocido';
        $query_hist = mysqli_query($conexion, "SELECT fecha, hora_ingreso, hora_salida, estado_marca FROM registros_asistencia WHERE id_empleado = '$id_historial' ORDER BY fecha DESC LIMIT 30");
    ?>
    <div class="modal fade" id="modalHistorial" tabindex="-1" aria-labelledby="modalHistorialLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content rounded-4 shadow border-0">
                <div class="modal-header bg-light border-0 pt-4 px-4">
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="modalHistorialLabel">
                            <i class="bi bi-clock-history text-primary me-2"></i>Historial de Asistencia
                        </h5>
                        <p class="text-muted mb-0 small mt-1">Colaborador: <b><?php echo htmlspecialchars($nombre_emp); ?></b></p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Fecha</th>
                                <th>Entrada</th>
                                <th>Salida</th>
                                <th class="pe-4">Estado del Marcaje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if(mysqli_num_rows($query_hist) > 0) {
                                while($row_h = mysqli_fetch_assoc($query_hist)) {
                                    echo "<tr>";
                                    echo "<td class='ps-4 text-secondary fw-semibold'>" . date("d/m/Y", strtotime($row_h['fecha'])) . "</td>";
                                    echo "<td><span class='text-success fw-bold'>" . ($row_h['hora_ingreso'] ? date("h:i A", strtotime($row_h['hora_ingreso'])) : '--:--') . "</span></td>";
                                    echo "<td><span class='text-primary fw-bold'>" . ($row_h['hora_salida'] ? date("h:i A", strtotime($row_h['hora_salida'])) : '--:--') . "</span></td>";
                                    
                                    $estados_arr = explode("|", $row_h['estado_marca']);
                                    $badges = "";
                                    foreach($estados_arr as $est) {
                                        $est = trim($est);
                                        $badges .= "<span class='badge bg-body-secondary text-dark border rounded-pill me-1'>$est</span> ";
                                    }
                                    echo "<td class='pe-4'>$badges</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='4' class='text-center py-5 text-muted'><i class='bi bi-inbox fs-2 d-block mb-2'></i>No hay registros de asistencia para este colaborador.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer border-0 bg-light px-4 pb-4 d-flex justify-content-between">
                    <a href="empleados.php?exportar_historial_excel=<?php echo urlencode($id_historial); ?>" class="btn btn-success rounded-pill px-4 shadow-sm">
                        <i class="bi bi-file-earmark-excel me-2"></i>Exportar Excel
                    </a>
                    <a href="empleados.php" class="btn btn-secondary rounded-pill px-4">Cerrar Historial</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var myModal = new bootstrap.Modal(document.getElementById('modalHistorial'), {
                keyboard: true
            });
            myModal.show();
            document.getElementById('modalHistorial').addEventListener('hidden.bs.modal', function () {
                window.location.href = 'empleados.php';
            });
        });
    </script>
    <?php endif; ?>

    <script>
        document.getElementById('inputBuscar').addEventListener('keyup', function() {
            let filtro = this.value.toLowerCase();
            let filas = document.querySelectorAll('#tablaEmpleados tbody tr');
            
            filas.forEach(fila => {
                if(fila.id === 'filaNoHay') return;

                let codigo = fila.cells[0].textContent.toLowerCase();
                let nombre = fila.cells[1].textContent.toLowerCase();
                let puesto = fila.cells[2].textContent.toLowerCase();
                
                if(codigo.includes(filtro) || nombre.includes(filtro) || puesto.includes(filtro)) {
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