<?php
// Cargar el autoloader de Composer para habilitar PhpSpreadsheet
require 'vendor/autoload.php';
include("conexion.php");

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if(!isset($_SESSION['usuario'])) { header("Location: login.php"); exit(); }

date_default_timezone_set('America/El_Salvador');

// --- FILTROS ---
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

// --- INICIALIZAR EL DOCUMENTO EXCEL ---
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Asistencia');

// --- ENCABEZADOS CORPORATIVOS ---
// Fila 1: Título Principal
$sheet->setCellValue('A1', 'MARCAJE CMD');
$sheet->mergeCells('A1:G1'); // Unir celdas de la A a la G
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Fila 2: Fecha de Generación
$fecha_actual = date('d/m/Y H:i:s');
$sheet->setCellValue('A2', 'Reporte generado el: ' . $fecha_actual);
$sheet->mergeCells('A2:G2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Fila 4: Cabeceras de la Tabla
$cabeceras = ['Código', 'Nombre', 'Puesto', 'Fecha', 'Hora Entrada', 'Hora Salida', 'Horas Trabajadas'];
$letraColumna = 'A';
foreach ($cabeceras as $cabecera) {
    $sheet->setCellValue($letraColumna . '4', $cabecera);
    $letraColumna++;
}

// Dar estilo a las cabeceras (Fondo oscuro, texto blanco, negrita)
$estiloCabecera = [
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF212529']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
];
$sheet->getStyle('A4:G4')->applyFromArray($estiloCabecera);

// --- RECORRER DATOS Y LLENAR CELDAS ---
$filaExcel = 5; // Empezamos a imprimir datos desde la fila 5

if (mysqli_num_rows($resultado) === 0) {
    $sheet->setCellValue('A5', 'No hay registros para el rango seleccionado.');
    $sheet->mergeCells('A5:G5');
} else {
    while ($fila = mysqli_fetch_assoc($resultado)) {
        $horas_trabajadas = '—';
        if (!empty($fila['hora_salida'])) {
            $inicio = strtotime($fila['fecha'] . ' ' . $fila['hora_ingreso']);
            $fin    = strtotime($fila['fecha'] . ' ' . $fila['hora_salida']);
            if ($fin > $inicio) {
                $horas_trabajadas = gmdate("H:i:s", $fin - $inicio);
            }
        }

        $sheet->setCellValue('A' . $filaExcel, $fila['id_empleado']);
        $sheet->setCellValue('B' . $filaExcel, $fila['nombre_completo']);
        $sheet->setCellValue('C' . $filaExcel, $fila['puesto']);
        $sheet->setCellValue('D' . $filaExcel, $fila['fecha']);
        $sheet->setCellValue('E' . $filaExcel, $fila['hora_ingreso']);
        $sheet->setCellValue('F' . $filaExcel, $fila['hora_salida'] ?? 'Pendiente');
        $sheet->setCellValue('G' . $filaExcel, $horas_trabajadas);
        
        $filaExcel++;
    }
}

// --- AUTOAJUSTAR ANCHO DE COLUMNAS ---
// Esta es la clave para evitar los ########
$columnasDimensionar = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
foreach ($columnasDimensionar as $colID) {
    $sheet->getColumnDimension($colID)->setAutoSize(true);
}

// --- DESCARGAR EL ARCHIVO ---
$nombre_archivo = "asistencia_" . $fecha_inicio . "_a_" . $fecha_fin . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $nombre_archivo . '"');
header('Cache-Control: max-age=0');

// Escribir el archivo en la salida del navegador
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
?>