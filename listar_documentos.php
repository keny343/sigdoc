<?php
include 'conexao.php';
$sql = "SELECT * FROM documentos";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Documentos</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="icon" type="image/svg+xml" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/icons/file-earmark-text.svg">
</head>
<body>
<div class="container mt-5">
    <h2>Lista de Documentos</h2>
    <a href="index.html" class="btn btn-link mb-3">Adicionar Novo Documento</a>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Título</th>
                <th>Autor</th>
                <th>Data</th>
                <th>Tipo</th>
                <th>Palavras-chave</th>
                <th>Resumo</th>
                <th>Arquivo</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['titulo']) ?></td>
                <td><?= htmlspecialchars($row['autor']) ?></td>
                <td><?= htmlspecialchars($row['data_criacao']) ?></td>
                <td><?= htmlspecialchars($row['tipo']) ?></td>
                <td><?= htmlspecialchars($row['palavras_chave']) ?></td>
                <td><?= htmlspecialchars($row['resumo']) ?></td>
                <td>
                    <?php if ($row['caminho_arquivo']): ?>
                        <a href="<?= htmlspecialchars($row['caminho_arquivo']) ?>" target="_blank">Baixar</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
<?php $conn->close(); ?> 