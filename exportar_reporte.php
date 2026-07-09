<?php
session_start();
include("conexion.php");
include("permisos_helper.php");

// Verificar que el usuario tenga permiso para exportar
requerirPermiso('exportar_excel', 'No tienes permiso para exportar reportes.');

// Cargar PhpSpreadsheet (asegúrate de haber ejecutado `composer require phpoffice/phpspreadsheet`)
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Obtener parámetros de filtro (ej. colaborador, fechas)
$colaborador_id = isset($_GET['colaborador']) ? (int)$_GET['colaborador'] : 0;
$fecha_inicio   = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fecha_fin      = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');

// Consulta de ejemplo (ajusta a tu estructura)
$sql = "SELECT r.fecha, r.entrada, r.salida, r.estado, c.nombre as colaborador
        FROM registros r
        JOIN colaboradores c ON r.colaborador_id = c.id
        WHERE r.fecha BETWEEN ? AND ?";
$params = [$fecha_inicio, $fecha_fin];
$types = "ss";

if ($colaborador_id > 0) {
    $sql .= " AND r.colaborador_id = ?";
    $params[] = $colaborador_id;
    $types .= "i";
}

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

// Crear el libro de Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Encabezados
$sheet->setCellValue('A1', 'Colaborador');
$sheet->setCellValue('B1', 'Fecha');
$sheet->setCellValue('C1', 'Entrada');
$sheet->setCellValue('D1', 'Salida');
$sheet->setCellValue('E1', 'Estado');

// Estilo para encabezados (negrita)
$sheet->getStyle('A1:E1')->getFont()->setBold(true);

// Rellenar datos
$fila = 2;
while ($row = mysqli_fetch_assoc($resultado)) {
    $sheet->setCellValue('A' . $fila, $row['colaborador']);
    $sheet->setCellValue('B' . $fila, $row['fecha']);
    $sheet->setCellValue('C' . $fila, $row['entrada']);
    $sheet->setCellValue('D' . $fila, $row['salida']);
    $sheet->setCellValue('E' . $fila, $row['estado']);
    $fila++;
}

// Autoajuste de columnas (opcional)
foreach (range('A', 'E') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Crear el writer y enviar al navegador
$writer = new Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="reporte_marcajes_' . date('Ymd_His') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;
?>