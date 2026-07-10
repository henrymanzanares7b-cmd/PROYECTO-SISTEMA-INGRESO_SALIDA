<?php
// exportar_permisos.php
require 'vendor/autoload.php';
include("conexion.php");

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// if(!isset($_SESSION['usuario'])) { header("Location: login.php"); exit(); }

date_default_timezone_set('America/El_Salvador');

// Consulta de Permisos con INNER JOIN
$sql = "SELECT sp.id_solicitud, e.nombre_completo, e.puesto, sp.tipo_permiso, sp.motivo, 
               sp.fecha_inicio, sp.fecha_fin, sp.estado, sp.fecha_solicitud 
        FROM solicitudes_permisos sp 
        INNER JOIN empleados e ON sp.id_empleado = e.id_empleado 
        ORDER BY sp.fecha_solicitud DESC";
$resultado = mysqli_query($conexion, $sql);

// --- INICIALIZAR EL DOCUMENTO EXCEL ---
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Permisos');

// --- ENCABEZADOS CORPORATIVOS ---
$sheet->setCellValue('A1', 'REPORTE DE PERMISOS');
$sheet->mergeCells('A1:G1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$fecha_actual = date('d/m/Y H:i:s');
$sheet->setCellValue('A2', 'Reporte generado el: ' . $fecha_actual);
$sheet->mergeCells('A2:G2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Fila 4: Cabeceras de la Tabla
$cabeceras = ['Empleado', 'Puesto', 'Tipo Permiso', 'Motivo', 'F. Inicio', 'F. Fin', 'Estado'];
$letraColumna = 'A';
foreach ($cabeceras as $cabecera) {
    $sheet->setCellValue($letraColumna . '4', $cabecera);
    $letraColumna++;
}

// Estilo de cabeceras
$estiloCabecera = [
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF212529']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
];
$sheet->getStyle('A4:G4')->applyFromArray($estiloCabecera);

// --- RECORRER DATOS Y LLENAR CELDAS ---
$filaExcel = 5;

if (mysqli_num_rows($resultado) === 0) {
    $sheet->setCellValue('A5', 'No hay registros de permisos.');
    $sheet->mergeCells('A5:G5');
} else {
    while ($fila = mysqli_fetch_assoc($resultado)) {
        $sheet->setCellValue('A' . $filaExcel, $fila['nombre_completo']);
        $sheet->setCellValue('B' . $filaExcel, $fila['puesto']);
        $sheet->setCellValue('C' . $filaExcel, $fila['tipo_permiso']);
        $sheet->setCellValue('D' . $filaExcel, $fila['motivo']);
        $sheet->setCellValue('E' . $filaExcel, $fila['fecha_inicio']);
        $sheet->setCellValue('F' . $filaExcel, $fila['fecha_fin']);
        $sheet->setCellValue('G' . $filaExcel, $fila['estado']);
        $filaExcel++;
    }
}

// --- AUTOAJUSTAR ANCHO DE COLUMNAS ---
$columnasDimensionar = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
foreach ($columnasDimensionar as $colID) {
    $sheet->getColumnDimension($colID)->setAutoSize(true);
}

// --- DESCARGAR EL ARCHIVO ---
$nombre_archivo = "Reporte_Permisos_" . date('Y_m_d') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $nombre_archivo . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
?>