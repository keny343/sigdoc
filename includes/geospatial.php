<?php
require_once __DIR__ . '/../conexao.php';

class Geospatial {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Adiciona localização a um documento
     */
    public function adicionarLocalizacaoDocumento($documentoId, $latitude, $longitude, $endereco = null) {
        $query = "UPDATE documentos 
                 SET localizacao = ST_GeomFromText('POINT(:lng :lat)', 4326), 
                     endereco = :endereco 
                 WHERE id = :id";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            ':lat' => $latitude,
            ':lng' => $longitude,
            ':endereco' => $endereco,
            ':id' => $documentoId
        ]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Encontra documentos próximos a uma localização
     */
    public function encontrarDocumentosProximos($latitude, $longitude, $raioKm = 10) {
        $query = "
            SELECT id, titulo, endereco, 
                   ST_Distance_Sphere(
                       localizacao, 
                       ST_GeomFromText('POINT(:lng :lat)', 4326)
                   ) / 1000 AS distancia_km
            FROM documentos
            WHERE ST_Distance_Sphere(
                localizacao, 
                ST_GeomFromText('POINT(:lng :lat)', 4326)
            ) <= :raio_metros
            ORDER BY distancia_km
        ";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            ':lat' => $latitude,
            ':lng' => $longitude,
            ':raio_metros' => $raioKm * 1000
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Registra um acesso com localização
     */
    public function registrarAcesso($usuarioId, $documentoId, $latitude, $longitude, $ip, $userAgent) {
        $query = "
            INSERT INTO acessos_geograficos 
            (usuario_id, documento_id, localizacao, endereco_ip, user_agent)
            VALUES (:usuario_id, :documento_id, ST_GeomFromText('POINT(:lng :lat)', 4326), :ip, :user_agent)
        ";
        
        $stmt = $this->pdo->prepare($query);
        return $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':documento_id' => $documentoId,
            ':lat' => $latitude,
            ':lng' => $longitude,
            ':ip' => $ip,
            ':user_agent' => $userAgent
        ]);
    }

    /**
     * Verifica se uma localização está dentro de um limite geográfico
     */
    public function verificarLimiteGeografico($limiteId, $latitude, $longitude) {
        $query = "
            SELECT COUNT(*) as esta_dentro
            FROM limites_geograficos
            WHERE id = :limite_id
            AND ST_Within(
                ST_GeomFromText('POINT(:lng :lat)', 4326),
                area
            )
            AND ativo = 1
        ";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            ':limite_id' => $limiteId,
            ':lat' => $latitude,
            ':lng' => $longitude
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['esta_dentro'] > 0;
    }
}

// Inicialização da classe
if (isset($pdo)) {
    $geospatial = new Geospatial($pdo);
}
?>
