<?php
require_once 'includes/db.php';
require_once 'includes/lang.php';
require_once 'fpdf.php';

session_start();
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit('Acesso negado.');
}

$format = isset($_GET['format']) && $_GET['format'] === 'pdf' ? 'pdf' : 'csv';

$stmt = $pdo->query("SELECT id, titulo, tipo, setor, categoria_acesso, area_origem, area_destino, prioridade, estado, versao_atual, data_upload FROM documentos ORDER BY id DESC");
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$headers = [
    'id' => 'ID',
    'titulo' => t('title'),
    'tipo' => t('type'),
    'setor' => t('sector'),
    'categoria_acesso' => t('category'),
    'area_origem' => t('origin_area'),
    'area_destino' => t('destination_area'),
    'prioridade' => t('priority'),
    'estado' => t('status'),
    'versao_atual' => t('version'),
    'data_upload' => t('upload'),
];

// CSV simples
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="documentos_' . date('Ymd_His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, array_values($headers));
    foreach ($docs as $doc) {
        $doc['categoria_acesso'] = t($doc['categoria_acesso']);
        $doc['prioridade'] = t($doc['prioridade']);
        $doc['estado'] = t($doc['estado']);
        fputcsv($output, array_values($doc));
    }
    fclose($output);
    exit;
}

// PDF com cores e estilo
class PDF extends FPDF {
    function Header() {
        $this->SetFont('Arial','B',14);
        $this->SetTextColor(0);
        $this->Cell(0,10,utf8_decode('Relatório de Documentos'),0,1,'C');
        $this->Ln(2);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->SetTextColor(100);
        $this->Cell(0,10,'Página '.$this->PageNo().'/{nb}',0,0,'C');
    }
}

// Largura das colunas
$cellWidths = [
    'id' => 10,
    'titulo' => 40,
    'tipo' => 20,
    'setor' => 25,
    'categoria_acesso' => 25,
    'area_origem' => 35,
    'area_destino' => 35,
    'prioridade' => 20,
    'estado' => 20,
    'versao_atual' => 15,
    'data_upload' => 32,
];

$pdf = new PDF('L','mm','A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','B',8);

// Cabeçalho colorido
$pdf->SetFillColor(0, 102, 204); // Azul
$pdf->SetTextColor(255); // Branco
foreach ($headers as $key => $header) {
    $pdf->Cell($cellWidths[$key], 8, utf8_decode($header), 1, 0, 'C', true);
}
$pdf->Ln();

// Corpo da tabela com cores alternadas
$pdf->SetFont('Arial','',7);
$fill = false; // Alternância de cor
foreach ($docs as $doc) {
    $pdf->SetFillColor(230, 240, 255); // Azul claro (linhas alternadas)
    $pdf->SetTextColor(0);
    
    $doc['categoria_acesso'] = t($doc['categoria_acesso']);
    $doc['prioridade'] = t($doc['prioridade']);
    $doc['estado'] = t($doc['estado']);

    foreach ($headers as $key => $header) {
        $texto = utf8_decode($doc[$key]);
        if (strlen($texto) > 50) {
            $texto = substr($texto, 0, 47) . '...';
        }
        $pdf->Cell($cellWidths[$key], 8, $texto, 1, 0, 'C', $fill);
    }
    $pdf->Ln();
    $fill = !$fill; // Alterna a cor da próxima linha
}

$pdf->Output('D', 'documentos_' . date('Ymd_His') . '.pdf');
exit;
