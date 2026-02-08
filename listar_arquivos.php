<?php
header('Content-Type: application/json; charset=utf-8');

function listar_drives() {
    $drives = [];
    foreach (range('C', 'Z') as $letra) {
        $drive = $letra . ':\\';
        if (is_dir($drive)) {
            $drives[] = [
                'nome' => $drive,
                'caminho' => $drive,
                'tipo' => 'drive'
            ];
        }
    }
    return $drives;
}

function listar_diretorio($caminho) {
    $itens = [];
    if (is_dir($caminho)) {
        $arquivos = scandir($caminho);
        foreach ($arquivos as $arquivo) {
            if ($arquivo == '.' || $arquivo == '..') continue;
            $item_path = rtrim($caminho, '\\') . '\\' . $arquivo;
            $itens[] = [
                'nome' => $arquivo,
                'caminho' => $item_path,
                'tipo' => is_dir($item_path) ? 'pasta' : 'arquivo'
            ];
        }
    }
    return $itens;
}

// Se for requisição para download de arquivo
if (isset($_GET['caminho'])) {
    $caminho = $_GET['caminho'];
    if (is_file($caminho)) {
        $nomeArquivo = basename($caminho);
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($caminho));
        flush();
        readfile($caminho);
        exit;
    } else {
        echo json_encode(listar_diretorio($caminho));
    }
} else {
    echo json_encode(listar_drives());
} 