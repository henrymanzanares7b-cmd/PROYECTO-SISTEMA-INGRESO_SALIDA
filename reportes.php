<?php
session_start();
include("conexion.php");

// Validación de sesión
if(!isset($_SESSION['usuario'])) { 
    header("Location: login.php"); 
    exit(); 
}

// Cargar PhpSpreadsheet
require_once 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// ==========================================
// LÓGICA PARA EXPORTAR A EXCEL (.XLSX)
// ==========================================
if (isset($_GET['exportar_excel']) && $_GET['exportar_excel'] == '1') {
    if (ob_get_length()) ob_clean(); 
    
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Monitor de Asistencia');

    $sheet->setCellValue('A1', 'MARCAJE CMD');
    $sheet->mergeCells('A1:F1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->setCellValue('A2', 'Reporte generado el: ' . date('d/m/Y H:i:s'));
    $sheet->mergeCells('A2:F2');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $cabeceras = ['Colaborador', 'Fecha', 'Ingreso', 'Salida', 'Horas Calculadas', 'Diagnóstico de Estado'];
    $letra = 'A';
    foreach ($cabeceras as $cabecera) {
        $sheet->setCellValue($letra . '4', $cabecera);
        $letra++;
    }
    
    $estiloCabecera = [
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0d6efd']], 
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ];
    $sheet->getStyle('A4:F4')->applyFromArray($estiloCabecera);

    $sql_export = "SELECT e.nombre_completo, r.fecha, r.hora_ingreso, r.hora_salida, r.estado_marca,
                   ROUND(TIME_TO_SEC(TIMEDIFF(r.hora_salida, r.hora_ingreso))/3600, 2) AS horas_trabajadas
                   FROM registros_asistencia r
                   INNER JOIN empleados e ON r.id_empleado = e.id_empleado
                   WHERE r.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                   ORDER BY r.fecha DESC";
    $resultado_export = mysqli_query($conexion, $sql_export);

    $filaExcel = 5;
    while ($fila = mysqli_fetch_assoc($resultado_export)) {
        $sheet->setCellValue('A' . $filaExcel, $fila['nombre_completo']);
        $sheet->setCellValue('B' . $filaExcel, date("d/m/Y", strtotime($fila['fecha'])));
        $sheet->setCellValue('C' . $filaExcel, $fila['hora_ingreso'] ? date("h:i A", strtotime($fila['hora_ingreso'])) : '--:--');
        $sheet->setCellValue('D' . $filaExcel, $fila['hora_salida'] ? date("h:i A", strtotime($fila['hora_salida'])) : '--:--');
        $sheet->setCellValue('E' . $filaExcel, $fila['horas_trabajadas'] ?? '0');
        $sheet->setCellValue('F' . $filaExcel, $fila['estado_marca']);
        $filaExcel++;
    }

    foreach (range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $nombre_archivo = 'Reporte_Asistencias_' . date('Y_m_d') . '.xlsx';
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
    <title>Sist.Control - Reportes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f6f9; }
        .navbar-custom { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); }
        .table-custom th { background-color: #f8f9fa; color: #6c757d; font-size: 0.85rem; text-transform: uppercase; border-bottom: none; }
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
                    <li class="nav-item"><a class="nav-link active fw-semibold" href="reportes.php">Analítica</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mb-5">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
            <div>
                <h2 class="fw-bold text-dark m-0">Monitor de Asistencia</h2>
                <p class="text-muted m-0">Análisis de la última semana (7 días)</p>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="reportes.php?exportar_excel=1" class="btn btn-success rounded-pill fw-bold shadow-sm px-4"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Descargar Excel</a>
            </div>
        </div>

        <div class="card shadow-sm rounded-4 border-0 mb-4">
            <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                <h5 class="fw-bold text-primary"><i class="bi bi-bar-chart-fill me-2"></i>Resumen de Horas Acumuladas (Últimos 7 días)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-custom align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Colaborador</th>
                                <th class="pe-4">Total de Horas Trabajadas</th>
                            </tr>
                        </thead>
                        <tbody class="border-top-0">
                            <?php
                            $sql_resumen = "SELECT e.nombre_completo,
                                           ROUND(SUM(TIME_TO_SEC(TIMEDIFF(r.hora_salida, r.hora_ingreso)))/3600, 2) AS total_horas
                                           FROM empleados e
                                           INNER JOIN registros_asistencia r ON e.id_empleado = r.id_empleado
                                           WHERE r.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                                             AND r.hora_salida IS NOT NULL
                                           GROUP BY e.id_empleado, e.nombre_completo
                                           ORDER BY total_horas DESC";
                            
                            $resultado_resumen = mysqli_query($conexion, $sql_resumen);
                            
                            if(mysqli_num_rows($resultado_resumen) > 0) {
                                while($fila_res = mysqli_fetch_assoc($resultado_resumen)) {
                                    echo "<tr>";
                                    echo "<td class='ps-4 fw-semibold text-dark'><i class='bi bi-person-circle text-muted me-2'></i>" . htmlspecialchars($fila_res['nombre_completo']) . "</td>";
                                    echo "<td class='pe-4'><span class='badge bg-primary rounded-pill shadow-sm px-3 fs-6'>" . ($fila_res['total_horas'] ?? '0.00') . " hrs</span></td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='2' class='text-center py-4 text-muted'>No hay horas registradas en los últimos 7 días.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="card shadow-sm rounded-4 border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-custom align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Colaborador</th>
                                <th>Fecha</th>
                                <th>Ingreso</th>
                                <th>Salida</th>
                                <th>Horas Calculadas</th>
                                <th class="pe-4">Diagnóstico de Estado</th>
                            </tr>
                        </thead>
                        <tbody class="border-top-0">
                            <?php
                            $sql_rep = "SELECT e.nombre_completo, r.fecha, r.hora_ingreso, r.hora_salida, r.estado_marca,
                                        ROUND(TIME_TO_SEC(TIMEDIFF(r.hora_salida, r.hora_ingreso))/3600, 2) AS horas_trabajadas
                                        FROM registros_asistencia r
                                        INNER JOIN empleados e ON r.id_empleado = e.id_empleado
                                        WHERE r.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                                        ORDER BY r.fecha DESC";
                            $resultado_rep = mysqli_query($conexion, $sql_rep);
                            while($fila = mysqli_fetch_assoc($resultado_rep)) {
                                
                                $estado = htmlspecialchars($fila['estado_marca']);
                                $badges_html = "";
                                
                                $etiquetas = explode("|", $estado);
                                foreach($etiquetas as $etiqueta) {
                                    $etiq = trim($etiqueta);
                                    if(str_contains(strtolower($etiq), 'tarde') || str_contains(strtolower($etiq), 'excedió')) {
                                        $badges_html .= "<span class='badge text-bg-danger rounded-pill me-1'>$etiq</span>";
                                    } elseif(str_contains(strtolower($etiq), 'temprana')) {
                                        $badges_html .= "<span class='badge text-bg-warning text-dark rounded-pill me-1'>$etiq</span>";
                                    } elseif(str_contains(strtolower($etiq), 'tiempo') || str_contains(strtolower($etiq), 'extra')) {
                                        $badges_html .= "<span class='badge text-bg-success rounded-pill me-1'>$etiq</span>";
                                    } else {
                                        $badges_html .= "<span class='badge bg-body-secondary text-dark border rounded-pill me-1'>$etiq</span>";
                                    }
                                }

                                echo "<tr>";
                                echo "<td class='ps-4 fw-semibold text-dark'><i class='bi bi-person-circle text-muted me-2'></i>" . htmlspecialchars($fila['nombre_completo']) . "</td>";
                                echo "<td><span class='text-muted'>" . date("d/m/Y", strtotime($fila['fecha'])) . "</span></td>";
                                echo "<td><span class='fw-bold text-success'>" . ($fila['hora_ingreso'] ? date("h:i A", strtotime($fila['hora_ingreso'])) : '--:--') . "</span></td>";
                                echo "<td><span class='fw-bold text-primary'>" . ($fila['hora_salida'] ? date("h:i A", strtotime($fila['hora_salida'])) : '--:--') . "</span></td>";
                                echo "<td><span class='badge bg-dark rounded-pill shadow-sm px-3'>" . ($fila['horas_trabajadas'] ?? '0') . " hrs</span></td>";
                                echo "<td class='pe-4'>$badges_html</td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>