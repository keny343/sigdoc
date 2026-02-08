<?php
/**
 * Sistema de Backup Automático - SIGDoc
 * 
 * Este script realiza backups automáticos de:
 * - Banco de dados (MySQL)
 * - Arquivos de documentos
 * - Configurações do sistema
 * 
 * Funcionalidades:
 * - Backup incremental
 * - Compressão de arquivos
 * - Limpeza automática de backups antigos
 * - Logs detalhados
 * - Notificações por email
 */

require_once 'includes/db.php';
require_once 'includes/notificar.php';
require_once 'includes/auth.php';

class BackupSystem {
    private $backupDir;
    private $maxBackups;
    private $logFile;
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->backupDir = 'backups/';
        $this->maxBackups = 30; // Manter últimos 30 backups
        $this->logFile = 'logs/backup_' . date('Y-m-d') . '.log';
        
        // Criar diretórios se não existirem
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        if (!is_dir('logs/')) {
            mkdir('logs/', 0755, true);
        }
    }
    
    /**
     * Executa backup completo do sistema
     */
    public function executarBackupCompleto() {
        $timestamp = date('Y-m-d_H-i-s'); // Data e hora de Angola com segundos
        $backupSubdir = $this->backupDir . $timestamp . DIRECTORY_SEPARATOR;
        if (!is_dir($backupSubdir)) {
            mkdir($backupSubdir, 0755, true);
        }
        $this->log("Iniciando backup completo - " . $timestamp);
        
        try {
            // 1. Backup do banco de dados
            $dbBackup = $this->backupBancoDados($timestamp, $backupSubdir);
            
            // 2. Backup dos arquivos
            $filesBackup = $this->backupArquivos($timestamp, $backupSubdir);
            
            // 3. Backup das configurações
            $configBackup = $this->backupConfiguracoes($timestamp, $backupSubdir);
            
            // 4. Criar arquivo de índice
            $this->criarIndiceBackup($timestamp, $dbBackup, $filesBackup, $configBackup, $backupSubdir);
            
            // 5. Limpar backups antigos
            $this->limparBackupsAntigos();
            
            // 6. Enviar notificação
            $this->notificarBackupSucesso($timestamp);
            
            $this->log("Backup completo finalizado com sucesso");
            return true;
            
        } catch (Exception $e) {
            $this->log("ERRO no backup: " . $e->getMessage());
            $this->notificarBackupErro($e->getMessage());
            return false;
        }
    }
    
    /**
     * Backup do banco de dados MySQL (PHP puro - compatível com hospedagem compartilhada)
     */
    private function backupBancoDados($timestamp, $backupSubdir) {
        $this->log("Iniciando backup do banco de dados...");
        
        $backupFile = $backupSubdir . "db_backup.sql";
        $sql = "-- Backup SIGDoc - " . date('Y-m-d H:i:s') . "\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        // Obter lista de tabelas
        $tables = $this->pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            // CREATE TABLE
            $createTable = $this->pdo->query("SHOW CREATE TABLE `" . str_replace('`', '``', $table) . "`")->fetch(PDO::FETCH_NUM);
            $sql .= "DROP TABLE IF EXISTS `" . str_replace('`', '``', $table) . "`;\n";
            $sql .= $createTable[1] . ";\n\n";
            
            // Dados
            $rows = $this->pdo->query("SELECT * FROM `" . str_replace('`', '``', $table) . "`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $colList = '`' . implode('`,`', array_map(function($c) { return str_replace('`', '``', $c); }, $columns)) . '`';
                foreach ($rows as $row) {
                    $values = array_map(function($v) {
                        return $v === null ? 'NULL' : $this->pdo->quote($v);
                    }, array_values($row));
                    $sql .= "INSERT INTO `" . str_replace('`', '``', $table) . "` ($colList) VALUES (" . implode(',', $values) . ");\n";
                }
                $sql .= "\n";
            }
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        file_put_contents($backupFile, $sql);
        
        // Comprimir arquivo
        $compressedFile = $backupFile . '.gz';
        $this->comprimirArquivo($backupFile, $compressedFile);
        unlink($backupFile);
        
        $this->log("Backup do banco de dados concluído: " . basename($compressedFile));
        return basename($compressedFile);
    }
    
    /**
     * Backup dos arquivos de documentos (PHP ZipArchive - compatível com hospedagem compartilhada)
     */
    private function backupArquivos($timestamp, $backupSubdir) {
        $this->log("Iniciando backup dos arquivos...");
        $backupFile = $backupSubdir . "files_backup.zip";
        
        if (!class_exists('ZipArchive')) {
            throw new Exception("Extensão ZipArchive não disponível");
        }
        
        $zip = new ZipArchive();
        if ($zip->open($backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Erro ao criar arquivo ZIP");
        }
        
        $baseDir = rtrim(__DIR__, '/\\') . DIRECTORY_SEPARATOR;
        $excludeDirs = ['backups', 'logs', '.git'];
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            $relativePath = substr($path, strlen($baseDir));
            $pathParts = explode(DIRECTORY_SEPARATOR, $relativePath);
            if (in_array($pathParts[0], $excludeDirs)) continue;
            if (strpos($relativePath, 'backups' . DIRECTORY_SEPARATOR) === 0) continue;
            
            if ($item->isDir()) {
                $zip->addEmptyDir($relativePath . '/');
            } else {
                $zip->addFile($path, $relativePath);
            }
        }
        
        $zip->close();
        $this->log("Backup dos arquivos concluído: " . basename($backupFile));
        return basename($backupFile);
    }
    
    /**
     * Backup das configurações do sistema (PHP ZipArchive - compatível com hospedagem compartilhada)
     */
    private function backupConfiguracoes($timestamp, $backupSubdir) {
        $this->log("Iniciando backup das configurações...");
        
        $configFiles = [
            'config_email.php',
            'config_ssl.php',
            'conexao.php',
            'includes/db.php',
            'includes/auth.php'
        ];
        
        $backupFile = $backupSubdir . "config_backup.zip";
        
        if (!class_exists('ZipArchive')) {
            throw new Exception("Extensão ZipArchive não disponível");
        }
        
        $zip = new ZipArchive();
        if ($zip->open($backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Erro ao criar arquivo ZIP");
        }
        
        $basePath = rtrim(__DIR__, '/\\') . DIRECTORY_SEPARATOR;
        foreach ($configFiles as $file) {
            $fullPath = $basePath . str_replace('/', DIRECTORY_SEPARATOR, $file);
            if (file_exists($fullPath)) {
                $zip->addFile($fullPath, 'config/' . basename($file));
            }
        }
        
        $zip->close();
        $this->log("Backup das configurações concluído: " . basename($backupFile));
        return basename($backupFile);
    }
    
    /**
     * Criar arquivo de índice do backup
     */
    private function criarIndiceBackup($timestamp, $dbFile, $filesFile, $configFile, $backupSubdir) {
        $indexFile = $backupSubdir . "backup_index.json";
        
        $index = [
            'timestamp' => $timestamp,
            'data_criacao' => date('Y-m-d H:i:s'),
            'arquivos' => [
                'banco_dados' => $dbFile,
                'arquivos' => $filesFile,
                'configuracoes' => $configFile
            ],
            'tamanho_total' => 0,
            'status' => 'completo'
        ];
        
        // Calcular tamanho total
        foreach ($index['arquivos'] as $file) {
            $filePath = $backupSubdir . $file;
            if (file_exists($filePath)) {
                $index['tamanho_total'] += filesize($filePath);
            }
        }
        
        file_put_contents($indexFile, json_encode($index, JSON_PRETTY_PRINT));
        $this->log("Índice do backup criado: " . basename($indexFile));
    }
    
    /**
     * Limpar backups antigos (remove subdiretórios por timestamp)
     */
    private function limparBackupsAntigos() {
        $this->log("Limpando backups antigos...");
        
        $subdirs = glob($this->backupDir . '*', GLOB_ONLYDIR);
        usort($subdirs, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        if (count($subdirs) > $this->maxBackups) {
            $toDelete = array_slice($subdirs, 0, count($subdirs) - $this->maxBackups);
            foreach ($toDelete as $dir) {
                $this->removerDiretorio($dir);
                $this->log("Backup antigo removido: " . basename($dir));
            }
        }
    }
    
    /**
     * Comprimir arquivo
     */
    private function comprimirArquivo($source, $destination) {
        $file = gzopen($destination, 'w9');
        gzwrite($file, file_get_contents($source));
        gzclose($file);
    }
    
    /**
     * Remover diretório recursivamente
     */
    private function removerDiretorio($dir) {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    $this->removerDiretorio($path);
                } else {
                    unlink($path);
                }
            }
            rmdir($dir);
        }
    }
    
    /**
     * Log de atividades
     */
    private function log($message) {
        $logEntry = date('Y-m-d H:i:s') . " - " . $message . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Notificar backup bem-sucedido
     */
    private function notificarBackupSucesso($timestamp) {
        $assunto = "Backup SIGDoc - Sucesso";
        $mensagem = "Backup do sistema realizado com sucesso em " . date('d/m/Y H:i:s') . "\n\n";
        $mensagem .= "Timestamp: {$timestamp}\n";
        $mensagem .= "Localização: {$this->backupDir}\n\n";
        $mensagem .= "Este é um backup automático do sistema.";
        
        // Enviar para administradores
        $this->enviarNotificacaoAdmin($assunto, $mensagem);
    }
    
    /**
     * Notificar erro no backup
     */
    private function notificarBackupErro($erro) {
        $assunto = "Backup SIGDoc - ERRO";
        $mensagem = "ERRO no backup do sistema em " . date('d/m/Y H:i:s') . "\n\n";
        $mensagem .= "Erro: {$erro}\n\n";
        $mensagem .= "Verifique os logs para mais detalhes.";
        
        // Enviar para administradores
        $this->enviarNotificacaoAdmin($assunto, $mensagem);
    }
    
    /**
     * Enviar notificação para administradores
     */
    private function enviarNotificacaoAdmin($assunto, $mensagem) {
        try {
            // Buscar emails dos administradores
            $stmt = $this->pdo->prepare("SELECT email FROM usuarios WHERE perfil = 'admin'");
            $stmt->execute();
            $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($admins as $email) {
                enviarEmail($email, $assunto, $mensagem);
            }
        } catch (Exception $e) {
            $this->log("Erro ao enviar notificação: " . $e->getMessage());
        }
    }
    
    /**
     * Baixar backup (arquivo individual ou completo em ZIP)
     * @param string $timestamp
     * @param string $tipo db|arquivos|config|completo
     */
    public function baixarBackup($timestamp, $tipo = 'completo') {
        $backupSubdir = $this->backupDir . $timestamp . DIRECTORY_SEPARATOR;
        $indexFile = $backupSubdir . 'backup_index.json';
        
        if (!file_exists($indexFile)) {
            throw new Exception('Backup não encontrado');
        }
        
        $index = json_decode(file_get_contents($indexFile), true);
        $arquivos = $index['arquivos'] ?? [];
        
        if ($tipo === 'completo') {
            $zipName = 'backup_completo_' . $timestamp . '.zip';
            $tempZip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup_' . uniqid() . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception('Erro ao criar arquivo para download');
            }
            foreach (['banco_dados' => 'db_backup.sql.gz', 'arquivos' => 'files_backup.zip', 'configuracoes' => 'config_backup.zip'] as $key => $default) {
                $file = $arquivos[$key] ?? $default;
                $path = $backupSubdir . $file;
                if (file_exists($path)) {
                    $zip->addFile($path, $file);
                }
            }
            $zip->addFile($indexFile, 'backup_index.json');
            $zip->close();
            $this->enviarArquivoDownload($tempZip, $zipName);
            @unlink($tempZip);
        } else {
            $map = ['db' => 'banco_dados', 'arquivos' => 'arquivos', 'config' => 'configuracoes'];
            $key = $map[$tipo] ?? null;
            if (!$key || empty($arquivos[$key])) {
                throw new Exception('Tipo de download inválido');
            }
            $path = $backupSubdir . $arquivos[$key];
            if (!file_exists($path)) {
                throw new Exception('Arquivo de backup não encontrado');
            }
            $this->enviarArquivoDownload($path, basename($arquivos[$key]));
        }
    }
    
    private function enviarArquivoDownload($filePath, $nomeExibicao) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($nomeExibicao) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        readfile($filePath);
        exit;
    }
    
    /**
     * Listar backups disponíveis
     */
    public function listarBackups() {
        $backups = [];
        $indexFiles = glob($this->backupDir . '*' . DIRECTORY_SEPARATOR . 'backup_index.json');
        
        foreach ($indexFiles as $indexFile) {
            $content = @file_get_contents($indexFile);
            $index = $content ? json_decode($content, true) : null;
            
            if ($index) {
                $index['_path'] = dirname($indexFile);
                $backups[] = $index;
            }
        }
        
        usort($backups, function($a, $b) {
            return strtotime($b['data_criacao'] ?? 0) - strtotime($a['data_criacao'] ?? 0);
        });
        
        return $backups;
    }
    
    /**
     * Restaurar backup
     */
    public function restaurarBackup($timestamp) {
        $this->log("Iniciando restauração do backup: {$timestamp}");
        
        $backupSubdir = $this->backupDir . $timestamp . DIRECTORY_SEPARATOR;
        $indexFile = $backupSubdir . "backup_index.json";
        
        if (!file_exists($indexFile)) {
            throw new Exception("Backup não encontrado");
        }
        
        $index = json_decode(file_get_contents($indexFile), true);
        
        try {
            // 1. Restaurar banco de dados
            $this->restaurarBancoDados($index['arquivos']['banco_dados'], $backupSubdir);
            
            // 2. Restaurar arquivos
            $this->restaurarArquivos($index['arquivos']['arquivos'], $backupSubdir);
            
            // 3. Restaurar configurações
            $this->restaurarConfiguracoes($index['arquivos']['configuracoes'], $backupSubdir);
            
            $this->log("Restauração concluída com sucesso");
            $this->notificarRestauracaoSucesso($timestamp);
            
            return true;
            
        } catch (Exception $e) {
            $this->log("ERRO na restauração: " . $e->getMessage());
            $this->notificarRestauracaoErro($e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Restaurar banco de dados (PHP puro - compatível com hospedagem compartilhada)
     */
    private function restaurarBancoDados($backupFile, $backupPath = null) {
        $this->log("Restaurando banco de dados...");
        $backupPath = $backupPath ?? $this->backupDir;
        $filePath = $backupPath . $backupFile;
        
        if (!file_exists($filePath)) {
            throw new Exception("Arquivo de backup do banco não encontrado");
        }
        
        $tempFile = $filePath . '.temp';
        $this->descomprimirArquivo($filePath, $tempFile);
        
        $sql = file_get_contents($tempFile);
        unlink($tempFile);
        
        // Executar SQL (dividir por ;\n - compatível com backup gerado)
        $statements = array_filter(array_map('trim', explode(";\n", $sql)), function($s) {
            return $s !== '' && strpos($s, '--') !== 0;
        });
        
        foreach ($statements as $stmt) {
            if ($stmt !== '') {
                try {
                    $this->pdo->exec($stmt . ';');
                } catch (PDOException $e) {
                    $this->log("Aviso ao executar SQL: " . substr($e->getMessage(), 0, 200));
                }
            }
        }
        
        $this->log("Banco de dados restaurado com sucesso");
    }
    
    /**
     * Restaurar arquivos (PHP ZipArchive - compatível com hospedagem compartilhada)
     * Suporta .zip (novo formato). Backups antigos .tar.gz não são suportados sem exec().
     */
    private function restaurarArquivos($backupFile, $backupPath = null) {
        $this->log("Restaurando arquivos...");
        $backupPath = $backupPath ?? $this->backupDir;
        $filePath = $backupPath . $backupFile;
        
        if (!file_exists($filePath)) {
            throw new Exception("Arquivo de backup dos arquivos não encontrado");
        }
        
        $ext = strtolower(pathinfo($backupFile, PATHINFO_EXTENSION));
        if ($ext === 'zip' && class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($filePath) === true) {
                $zip->extractTo('.');
                $zip->close();
            } else {
                throw new Exception("Erro ao abrir arquivo ZIP");
            }
        } else {
            throw new Exception("Formato de backup não suportado (requer .zip). Backups .tar.gz antigos não são compatíveis com esta hospedagem.");
        }
        
        $this->log("Arquivos restaurados com sucesso");
    }
    
    /**
     * Restaurar configurações (PHP ZipArchive - compatível com hospedagem compartilhada)
     */
    private function restaurarConfiguracoes($backupFile, $backupPath = null) {
        $this->log("Restaurando configurações...");
        $backupPath = $backupPath ?? $this->backupDir;
        $filePath = $backupPath . $backupFile;
        
        if (!file_exists($filePath)) {
            throw new Exception("Arquivo de backup das configurações não encontrado");
        }
        
        $ext = strtolower(pathinfo($backupFile, PATHINFO_EXTENSION));
        if ($ext === 'zip' && class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($filePath) === true) {
                $tempDir = sys_get_temp_dir() . '/config_restore_' . uniqid() . '/';
                mkdir($tempDir, 0755, true);
                $zip->extractTo($tempDir);
                $zip->close();
                $configToIncludes = ['db.php', 'auth.php'];
                $configDir = $tempDir . 'config/';
                if (is_dir($configDir)) {
                    foreach (scandir($configDir) as $f) {
                        if ($f === '.' || $f === '..') continue;
                        $src = $configDir . $f;
                        $dest = in_array($f, $configToIncludes) ? 'includes/' . $f : $f;
                        if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);
                        copy($src, $dest);
                    }
                }
                $this->removerDiretorio($tempDir);
            } else {
                throw new Exception("Erro ao abrir arquivo ZIP");
            }
        } else {
            throw new Exception("Formato de backup não suportado (requer .zip)");
        }
        
        $this->log("Configurações restauradas com sucesso");
    }
    
    /**
     * Descomprimir arquivo
     */
    private function descomprimirArquivo($source, $destination) {
        $file = gzopen($source, 'r');
        $content = '';
        while (!gzeof($file)) {
            $content .= gzread($file, 4096);
        }
        gzclose($file);
        file_put_contents($destination, $content);
    }
    
    /**
     * Notificar restauração bem-sucedida
     */
    private function notificarRestauracaoSucesso($timestamp) {
        $assunto = "Restauração SIGDoc - Sucesso";
        $mensagem = "Restauração do sistema realizada com sucesso em " . date('d/m/Y H:i:s') . "\n\n";
        $mensagem .= "Backup restaurado: {$timestamp}\n\n";
        $mensagem .= "O sistema foi restaurado para o estado do backup.";
        
        $this->enviarNotificacaoAdmin($assunto, $mensagem);
    }
    
    /**
     * Notificar erro na restauração
     */
    private function notificarRestauracaoErro($erro) {
        $assunto = "Restauração SIGDoc - ERRO";
        $mensagem = "ERRO na restauração do sistema em " . date('d/m/Y H:i:s') . "\n\n";
        $mensagem .= "Erro: {$erro}\n\n";
        $mensagem .= "Verifique os logs para mais detalhes.";
        
        $this->enviarNotificacaoAdmin($assunto, $mensagem);
    }
}

// Exibir erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Definir timezone de Angola
date_default_timezone_set('Africa/Luanda');

// Corrigir acesso a $_GET['acao']
$acao = $_GET['acao'] ?? '';

// Download deve ser tratado ANTES de qualquer saída HTML (headers)
if ($acao === 'baixar' && isset($_GET['timestamp'])) {
    try {
        $backup = new BackupSystem();
        $tipo = $_GET['tipo'] ?? 'completo';
        $backup->baixarBackup($_GET['timestamp'], $tipo);
    } catch (Exception $e) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Erro</title></head><body>';
        echo '<p style="color:red;">Erro: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<a href="?acao=listar">Voltar aos backups</a></body></html>';
    }
    exit;
}

?><!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistema de Backup - SIGDoc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="includes/style.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="./">SIGDoc</a>
    <span class="navbar-text text-white">Backup do Sistema</span>
  </div>
</nav>
<div class="container">
<?php
switch ($acao) {
    case 'executar':
        $backup = new BackupSystem();
        $resultado = $backup->executarBackupCompleto();
        $dataHora = date('d/m/Y H:i:s');
        if ($resultado) {
            echo '<div class="alert alert-success mt-4">'
                .'<h4 class="alert-heading">Backup realizado com sucesso!</h4>'
                .'<p>Data e hora do backup: <b>'.$dataHora.'</b></p>'
                .'<p>Verifique a pasta <code>backups/</code> para os arquivos gerados.</p>'
                .'<a href="?acao=listar" class="btn btn-outline-primary mt-2">Ver Backups</a>'
                .'</div>';
        } else {
            echo '<div class="alert alert-danger mt-4">Ocorreu um erro durante o backup. Veja os logs para detalhes.</div>';
        }
        break;
    case 'listar':
        $backup = new BackupSystem();
        $backups = $backup->listarBackups();
        echo '<div class="card mt-4">';
        echo '<div class="card-header bg-primary text-white"><h5 class="mb-0">Backups Disponíveis</h5></div>';
        echo '<div class="card-body">';
        if (empty($backups)) {
            echo '<div class="alert alert-warning">Nenhum backup encontrado.</div>';
        } else {
            echo '<div class="table-responsive">';
            echo '<table class="table table-striped table-hover align-middle">';
            echo '<thead class="table-primary"><tr><th>Data</th><th>Timestamp</th><th>Tamanho</th><th>Ações</th></tr></thead>';
            echo '<tbody>';
            foreach ($backups as $backup) {
                $tamanho = number_format($backup['tamanho_total'] / 1024 / 1024, 2) . " MB";
                echo '<tr>';
                echo '<td>' . $backup['data_criacao'] . '</td>';
                echo '<td><code>' . $backup['timestamp'] . '</code></td>';
                echo '<td>' . $tamanho . '</td>';
                echo '<td class="text-nowrap">';
                echo '<div class="btn-group" role="group">';
                echo '<a href="?acao=baixar&timestamp=' . urlencode($backup['timestamp']) . '&tipo=completo" class="btn btn-sm btn-primary" title="Baixar backup completo">⬇ Baixar</a>';
                echo '<button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false"><span class="visually-hidden">Opções</span></button>';
                echo '<ul class="dropdown-menu">';
                echo '<li><a class="dropdown-item" href="?acao=baixar&timestamp=' . urlencode($backup['timestamp']) . '&tipo=db">Banco de dados (.sql.gz)</a></li>';
                echo '<li><a class="dropdown-item" href="?acao=baixar&timestamp=' . urlencode($backup['timestamp']) . '&tipo=arquivos">Arquivos (.zip)</a></li>';
                echo '<li><a class="dropdown-item" href="?acao=baixar&timestamp=' . urlencode($backup['timestamp']) . '&tipo=config">Configurações (.zip)</a></li>';
                echo '</ul>';
                echo '</div>';
                echo ' <a href="?acao=restaurar&timestamp=' . urlencode($backup['timestamp']) . '" class="btn btn-sm btn-warning" onclick="return confirm(\'Tem certeza? Esta ação irá sobrescrever dados atuais.\')">Restaurar</a>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '<a href="?acao=executar" class="btn btn-success mt-3">Executar Novo Backup</a>';
        echo '</div></div>';
        break;
    case 'restaurar':
        if (isset($_GET['timestamp'])) {
            $backup = new BackupSystem();
            try {
                $backup->restaurarBackup($_GET['timestamp']);
                echo '<div class="alert alert-success mt-4">Restauração executada com sucesso!</div>';
            } catch (Exception $e) {
                echo '<div class="alert alert-danger mt-4">Erro na restauração: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        }
        echo '<a href="?acao=listar" class="btn btn-outline-primary mt-3">Voltar para Backups</a>';
        break;
    default:
        echo '<div class="card mt-5 shadow">';
        echo '<div class="card-header bg-primary text-white"><h4 class="mb-0">Sistema de Backup - SIGDoc</h4></div>';
        echo '<div class="card-body">';
        echo '<p>Bem-vindo ao painel de backup do sistema SIGDoc. Aqui você pode realizar backups completos do sistema, restaurar versões anteriores e visualizar o histórico de backups.</p>';
        echo '<a href="?acao=executar" class="btn btn-success me-2">Executar Backup Agora</a>';
        echo '<a href="?acao=listar" class="btn btn-outline-primary">Ver Backups</a>';
        echo '</div></div>';
        break;
}
?>
</div>
<footer class="footer bg-dark text-white-50" style="margin-top:40px; padding:20px 0; background:#343a40; color:#fff; text-align:center; border-radius:0 0 8px 8px; position:fixed; left:0; bottom:0; width:100%; z-index:100; font-size:1rem;">
  <div class="container text-center">
    <span>© 2025 SIGDoc - Sistema Integrado de Gestão Documental</span>
  </div>
</footer>
</body>
</html> 