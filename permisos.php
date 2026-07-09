<?php
session_start();
include("conexion.php");

if(!isset($_SESSION['usuario'])) { 
    header("Location: login.php"); 
    exit(); 
}

// Cargar PhpSpreadsheet (Asegúrate de tener la carpeta vendor generada por Composer)
require_once 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// =====================================================================
// BLOQUE DE EXPORTACIÓN A EXCEL (.XLSX) PARA PERMISOS
// =====================================================================
if (isset($_GET['exportar_permisos']) && $_GET['exportar_permisos'] == 'true') {
    if (ob_get_length()) ob_clean(); // Limpiar el buffer de salida para evitar archivos corruptos
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Registro de Permisos');

    // Título Principal
    $sheet->setCellValue('A1', 'MARCAJE CMD');
    $sheet->mergeCells('A1:E1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Subtítulo con fecha
    $sheet->setCellValue('A2', 'Reporte generado el: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:E2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Cabeceras de la tabla
    $cabeceras = ['Colaborador', 'Tipo de Permiso', 'Modalidad (Día/Horas)', 'Fechas', 'Estado'];
    $letra = 'A';
    foreach ($cabeceras as $cabecera) {
        $sheet->setCellValue($letra . '4', $cabecera);
        $letra++;
    }
    
    // Estilos de la cabecera
    $estiloCabecera = [
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0d6efd']], 
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ];
    $sheet->getStyle('A4:E4')->applyFromArray($estiloCabecera);

    // Consulta a la base de datos (Ajusta 'solicitudes_permisos' si tu tabla se llama distinto)
    $query = "SELECT e.nombre_completo AS colaborador, p.tipo_permiso, p.modalidad, p.fechas, p.estado 
              FROM solicitudes_permisos p
              INNER JOIN empleados e ON p.id_empleado = e.id_empleado 
              ORDER BY p.id DESC";

    $resultado = mysqli_query($conexion, $query);

    $filaExcel = 5;
    if ($resultado) {
        while ($row = mysqli_fetch_assoc($resultado)) {
            $sheet->setCellValue('A' . $filaExcel, $row['colaborador']);
            $sheet->setCellValue('B' . $filaExcel, $row['tipo_permiso']);
            $sheet->setCellValue('C' . $filaExcel, $row['modalidad']);
            $sheet->setCellValue('D' . $filaExcel, $row['fechas']);
            $sheet->setCellValue('E' . $filaExcel, $row['estado']);
            $filaExcel++;
        }
    }

    // Autoajustar el ancho de las columnas
    foreach (range('A', 'E') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Forzar la descarga del archivo XLSX
    $nombre_archivo = 'Reporte_Permisos_' . date('Y_m_d_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}
// =====================================================================

/*
 * Este módulo maneja NIVELES JERÁRQUICOS de empleados (no usuarios del login).
 * Se auto-crea/actualiza el esquema la primera vez que se visita esta página.
 */

// --- AUTO-MIGRACIÓN (idempotente, segura de ejecutar en cada carga) ---
mysqli_query($conexion, "CREATE TABLE IF NOT EXISTS niveles_permisos (
    nivel VARCHAR(50) NOT NULL PRIMARY KEY,
    ver_reportes TINYINT(1) NOT NULL DEFAULT 0,
    exportar_excel TINYINT(1) NOT NULL DEFAULT 0,
    gestionar_empleados TINYINT(1) NOT NULL DEFAULT 0,
    ver_todos_registros TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Verificar si la columna "nivel" ya existe en empleados; si no, agregarla
$check_columna = mysqli_query($conexion, "SHOW COLUMNS FROM empleados LIKE 'nivel'");
if (mysqli_num_rows($check_columna) === 0) {
    mysqli_query($conexion, "ALTER TABLE empleados ADD COLUMN nivel VARCHAR(50) NOT NULL DEFAULT 'Operativo'");
}

// Si la tabla de niveles está vacía, sembrar niveles base
$check_niveles = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM niveles_permisos");
if (mysqli_fetch_assoc($check_niveles)['total'] == 0) {
    mysqli_query($conexion, "INSERT INTO niveles_permisos (nivel, ver_reportes, exportar_excel, gestionar_empleados, ver_todos_registros) VALUES
        ('Operativo', 0, 0, 0, 0),
        ('Supervisor', 1, 0, 0, 1),
        ('Gerencia', 1, 1, 1, 1),
        ('Administrador', 1, 1, 1, 1)");
}

$mensaje = "";
$tipo_alerta = "";

// --- AGREGAR NUEVO NIVEL ---
if (isset($_POST['agregar_nivel'])) {
    $nivel_nuevo = trim($_POST['nivel_nuevo']);
    if ($nivel_nuevo !== '') {
        $ver_reportes = isset($_POST['ver_reportes']) ? 1 : 0;
        $exportar_excel = isset($_POST['exportar_excel']) ? 1 : 0;
        $gestionar_empleados = isset($_POST['gestionar_empleados']) ? 1 : 0;
        $ver_todos_registros = isset($_POST['ver_todos_registros']) ? 1 : 0;

        $sql = "INSERT INTO niveles_permisos (nivel, ver_reportes, exportar_excel, gestionar_empleados, ver_todos_registros)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE ver_reportes=VALUES(ver_reportes), exportar_excel=VALUES(exportar_excel),
                gestionar_empleados=VALUES(gestionar_empleados), ver_todos_registros=VALUES(ver_todos_registros)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "siiii", $nivel_nuevo, $ver_reportes, $exportar_excel, $gestionar_empleados, $ver_todos_registros);
        if (mysqli_stmt_execute($stmt)) {
            $mensaje = "Nivel \"" . htmlspecialchars($nivel_nuevo) . "\" guardado correctamente.";
            $tipo_alerta = "alert-success";
        } else {
            $mensaje = "Error al guardar el nivel: " . mysqli_error($conexion);
            $tipo_alerta = "alert-danger";
        }
    }
}

// --- ACTUALIZAR PERMISOS DE UN NIVEL EXISTENTE ---
if (isset($_POST['actualizar_nivel'])) {
    $nivel = $_POST['nivel'];
    $ver_reportes = isset($_POST['ver_reportes']) ? 1 : 0;
    $exportar_excel = isset($_POST['exportar_excel']) ? 1 : 0;
    $gestionar_empleados = isset($_POST['gestionar_empleados']) ? 1 : 0;
    $ver_todos_registros = isset($_POST['ver_todos_registros']) ? 1 : 0;

    $sql = "UPDATE niveles_permisos SET ver_reportes=?, exportar_excel=?, gestionar_empleados=?, ver_todos_registros=? WHERE nivel=?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iiiis", $ver_reportes, $exportar_excel, $gestionar_empleados, $ver_todos_registros, $nivel);
    if (mysqli_stmt_execute($stmt)) {
        $mensaje = "Permisos de \"" . htmlspecialchars($nivel) . "\" actualizados.";
        $tipo_alerta = "alert-success";
    } else {
        $mensaje = "Error al actualizar: " . mysqli_error($conexion);
        $tipo_alerta = "alert-danger";
    }
}

// --- ELIMINAR NIVEL (no permite borrar si hay empleados usándolo) ---
if (isset($_GET['eliminar_nivel'])) {
    $nivel_eliminar = $_GET['eliminar_nivel'];

    $sql_check = "SELECT COUNT(*) AS total FROM empleados WHERE nivel = ?";
    $stmt_check = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "s", $nivel_eliminar);
    mysqli_stmt_execute($stmt_check);
    $en_uso = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check))['total'];

    if ($en_uso > 0) {
        $mensaje = "No se puede eliminar \"" . htmlspecialchars($nivel_eliminar) . "\": hay $en_uso empleado(s) asignados a este nivel.";
        $tipo_alerta = "alert-warning";
    } else {
        $sql_del = "DELETE FROM niveles_permisos WHERE nivel = ?";
        $stmt_del = mysqli_prepare($conexion, $sql_del);
        mysqli_stmt_bind_param($stmt_del, "s", $nivel_eliminar);
        mysqli_stmt_execute($stmt_del);
        $mensaje = "Nivel eliminado correctamente.";
        $tipo_alerta = "alert-success";
    }
}

// --- ASIGNAR NIVEL A UN EMPLEADO ---
if (isset($_POST['asignar_nivel'])) {
    $id_empleado = $_POST['id_empleado'];
    $nivel_asignado = $_POST['nivel_asignado'];

    $sql = "UPDATE empleados SET nivel = ? WHERE id_empleado = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $nivel_asignado, $id_empleado);
    if (mysqli_stmt_execute($stmt)) {
        $mensaje = "Nivel de " . htmlspecialchars($id_empleado) . " actualizado a \"" . htmlspecialchars($nivel_asignado) . "\".";
        $tipo_alerta = "alert-success";
    } else {
        $mensaje = "Error al asignar nivel: " . mysqli_error($conexion);
        $tipo_alerta = "alert-danger";
    }
}

// --- DATOS PARA RENDER ---
$niveles = mysqli_query($conexion, "SELECT * FROM niveles_permisos ORDER BY nivel ASC");
$empleados_lista = mysqli_query($conexion, "SELECT id_empleado, nombre_completo, puesto, nivel FROM empleados ORDER BY nombre_completo ASC");
$niveles_para_select = mysqli_query($conexion, "SELECT nivel FROM niveles_permisos ORDER BY nivel ASC");
$lista_niveles_nombres = [];
while ($n = mysqli_fetch_assoc($niveles_para_select)) {
    $lista_niveles_nombres[] = $n['nivel'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Permisos por Nivel Jerárquico</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
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
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="empleados.php">Personal</a></li>
                    <li class="nav-item"><a class="nav-link" href="scan.php">Escáner QR</a></li>
                    <li class="nav-item"><a class="nav-link" href="reportes.php">Analítica</a></li>
                    <li class="nav-item"><a class="nav-link active" href="permisos.php">Permisos</a></li>
                </ul>
                <div class="d-flex align-items-center">
                    <span class="text-white me-3">Hola, <b><?php echo htmlspecialchars($_SESSION['nombre_admin'] ?? $_SESSION['usuario']); ?></b></span>
                    <a href="logout.php" class="btn btn-danger btn-sm">Cerrar Sesión</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mb-5">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Permisos por Nivel Jerárquico</h2>
            <a href="permisos.php?exportar_permisos=true" class="btn btn-success shadow-sm">
                <i class="bi bi-file-earmark-spreadsheet me-2"></i> Exportar XLSX
            </a>
        </div>

        <?php if ($mensaje !== "") { ?>
            <div class="alert <?php echo $tipo_alerta; ?> shadow-sm"><?php echo $mensaje; ?></div>
        <?php } ?>

        <div class="row">
            <div class="col-lg-7 mb-4">
                <div class="card shadow-sm mb-4 border-0 rounded-4">
                    <div class="card-header bg-dark text-white fw-bold pt-3 pb-2 px-4 border-0">Niveles Existentes</div>
                    <div class="card-body p-0">
                        <table class="table table-striped align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Nivel</th>
                                    <th class="text-center">Ver Reportes</th>
                                    <th class="text-center">Exportar Excel</th>
                                    <th class="text-center">Gestionar Empleados</th>
                                    <th class="text-center">Ver Todos los Registros</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i_niv = 0; while ($niv = mysqli_fetch_assoc($niveles)) { $i_niv++; $fid = "form_nivel_$i_niv"; ?>
                                <tr>
                                    <td class="ps-4">
                                        <form id="<?php echo $fid; ?>" method="POST" action="permisos.php"></form>
                                        <input type="hidden" form="<?php echo $fid; ?>" name="nivel" value="<?php echo htmlspecialchars($niv['nivel']); ?>">
                                        <b><?php echo htmlspecialchars($niv['nivel']); ?></b>
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" form="<?php echo $fid; ?>" name="ver_reportes" class="form-check-input" <?php echo $niv['ver_reportes'] ? 'checked' : ''; ?>>
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" form="<?php echo $fid; ?>" name="exportar_excel" class="form-check-input" <?php echo $niv['exportar_excel'] ? 'checked' : ''; ?>>
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" form="<?php echo $fid; ?>" name="gestionar_empleados" class="form-check-input" <?php echo $niv['gestionar_empleados'] ? 'checked' : ''; ?>>
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox" form="<?php echo $fid; ?>" name="ver_todos_registros" class="form-check-input" <?php echo $niv['ver_todos_registros'] ? 'checked' : ''; ?>>
                                    </td>
                                    <td class="text-nowrap pe-4">
                                        <button type="submit" form="<?php echo $fid; ?>" name="actualizar_nivel" value="1" class="btn btn-sm btn-primary" title="Guardar">
                                            <i class="bi bi-save"></i>
                                        </button>
                                        <a href="permisos.php?eliminar_nivel=<?php echo urlencode($niv['nivel']); ?>"
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('¿Eliminar el nivel <?php echo htmlspecialchars($niv['nivel']); ?>?')"
                                           title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-header bg-primary text-white fw-bold pt-3 pb-2 px-4 border-0">Crear Nuevo Nivel</div>
                    <div class="card-body">
                        <form method="POST" action="permisos.php" class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Nombre del Nivel</label>
                                <input type="text" name="nivel_nuevo" class="form-control" placeholder="Ej. Coordinador" required>
                            </div>
                            <div class="col-6 col-md-3 form-check ms-3">
                                <input type="checkbox" class="form-check-input" name="ver_reportes" id="cr">
                                <label class="form-check-label" for="cr">Ver Reportes</label>
                            </div>
                            <div class="col-6 col-md-3 form-check">
                                <input type="checkbox" class="form-check-input" name="exportar_excel" id="ce">
                                <label class="form-check-label" for="ce">Exportar Excel</label>
                            </div>
                            <div class="col-6 col-md-3 form-check">
                                <input type="checkbox" class="form-check-input" name="gestionar_empleados" id="cg">
                                <label class="form-check-label" for="cg">Gestionar Empleados</label>
                            </div>
                            <div class="col-6 col-md-3 form-check">
                                <input type="checkbox" class="form-check-input" name="ver_todos_registros" id="cv">
                                <label class="form-check-label" for="cv">Ver Todos los Registros</label>
                            </div>
                            <div class="col-12 mt-4">
                                <button type="submit" name="agregar_nivel" class="btn btn-success rounded-pill px-4">
                                    <i class="bi bi-plus-circle me-2"></i> Crear Nivel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 mb-4">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-header bg-dark text-white fw-bold pt-3 pb-2 px-4 border-0">Asignar Nivel a Empleados</div>
                    <div class="card-body p-0">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Empleado</th>
                                    <th>Nivel Actual</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i_emp = 0; while ($emp = mysqli_fetch_assoc($empleados_lista)) { $i_emp++; $fid2 = "form_emp_$i_emp"; ?>
                                <tr>
                                    <td class="ps-4">
                                        <form id="<?php echo $fid2; ?>" method="POST" action="permisos.php"></form>
                                        <b><?php echo htmlspecialchars($emp['id_empleado']); ?></b><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($emp['nombre_completo']); ?></small>
                                        <input type="hidden" form="<?php echo $fid2; ?>" name="id_empleado" value="<?php echo htmlspecialchars($emp['id_empleado']); ?>">
                                    </td>
                                    <td>
                                        <select form="<?php echo $fid2; ?>" name="nivel_asignado" class="form-select form-select-sm shadow-none">
                                            <?php foreach ($lista_niveles_nombres as $nombre_nivel) { ?>
                                                <option value="<?php echo htmlspecialchars($nombre_nivel); ?>"
                                                    <?php echo ($emp['nivel'] === $nombre_nivel) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($nombre_nivel); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </td>
                                    <td class="pe-4">
                                        <button type="submit" form="<?php echo $fid2; ?>" name="asignar_nivel" value="1" class="btn btn-sm btn-primary">
                                            <i class="bi bi-check2"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>