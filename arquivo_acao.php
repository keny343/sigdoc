<?php
header('Content-Type: application/json; charset=utf-8');

function apagar($caminho) {
    if (is_dir($caminho)) {
        $files = array_diff(scandir($caminho), array('.', '..'));
        foreach ($files as $file) {
            apagar($caminho . DIRECTORY_SEPARATOR . $file);
        }
        return rmdir($caminho);
    } elseif (is_file($caminho)) {
        return unlink($caminho);
    }
    return false;
}

function copiar($origem, $destino) {
    if (is_dir($origem)) {
        if (!is_dir($destino)) {
            mkdir($destino, 0777, true);
        }
        $files = array_diff(scandir($origem), array('.', '..'));
        foreach ($files as $file) {
            copiar($origem . DIRECTORY_SEPARATOR . $file, $destino . DIRECTORY_SEPARATOR . $file);
        }
        return true;
    } elseif (is_file($origem)) {
        return copy($origem, $destino);
    }
    return false;
}

$acao = $_POST['acao'] ?? '';
$response = ['success' => false, 'mensagem' => 'Ação inválida'];

if ($acao === 'apagar' && isset($_POST['caminho'])) {
    $caminho = $_POST['caminho'];
    $ok = apagar($caminho);
    $response = [
        'success' => $ok,
        'mensagem' => $ok ? 'Apagado com sucesso!' : 'Erro ao apagar.'
    ];
} elseif ($acao === 'copiar' && isset($_POST['origem'], $_POST['destino'])) {
    $ok = copiar($_POST['origem'], $_POST['destino']);
    $response = [
        'success' => $ok,
        'mensagem' => $ok ? 'Copiado com sucesso!' : 'Erro ao copiar.'
    ];
} elseif ($acao === 'nova_pasta' && isset($_POST['caminho'])) {
    $caminho = $_POST['caminho'];
    if (!is_dir($caminho)) {
        $ok = mkdir($caminho, 0777, true);
        $response = [
            'success' => $ok,
            'mensagem' => $ok ? 'Pasta criada com sucesso!' : 'Erro ao criar pasta.'
        ];
    } else {
        $response = [
            'success' => false,
            'mensagem' => 'Já existe uma pasta com esse nome.'
        ];
    }
} elseif ($acao === 'novo_arquivo' && isset($_POST['caminho'])) {
    $caminho = $_POST['caminho'];
    if (!file_exists($caminho)) {
        $ok = touch($caminho);
        $response = [
            'success' => $ok,
            'mensagem' => $ok ? 'Arquivo criado com sucesso!' : 'Erro ao criar arquivo.'
        ];
    } else {
        $response = [
            'success' => false,
            'mensagem' => 'Já existe um arquivo com esse nome.'
        ];
    }
}
echo json_encode($response); 