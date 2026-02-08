<?php
include 'conexao.php';

$titulo = $_POST['titulo'];
$autor = $_POST['autor'];
$data_criacao = $_POST['data_criacao'];
$tipo = $_POST['tipo'];
$palavras_chave = $_POST['palavras_chave'];
$resumo = $_POST['resumo'];

// Upload do arquivo
$diretorio = "uploads/";
$arquivo_nome = basename($_FILES["arquivo"]["name"]);
$caminho_arquivo = $diretorio . uniqid() . "_" . $arquivo_nome;

if (move_uploaded_file($_FILES["arquivo"]["tmp_name"], $caminho_arquivo)) {
    // Inserir no banco
    $sql = "INSERT INTO documentos (titulo, autor, data_criacao, tipo, palavras_chave, caminho_arquivo, resumo)
    VALUES ('$titulo', '$autor', '$data_criacao', '$tipo', '$palavras_chave', '$caminho_arquivo', '$resumo')";

    if ($conn->query($sql) === TRUE) {
        echo "Documento inserido com sucesso! <a href='listar_documentos.php'>Ver documentos</a>";
    } else {
        echo "Erro: " . $sql . "<br>" . $conn->error;
    }
} else {
    echo "Erro ao fazer upload do arquivo.";
}
$conn->close();
?> 