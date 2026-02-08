<?php
// cache.php - Sistema de Cache para Performance

class Cache {
    private $cache_dir;
    private $default_ttl;
    
    public function __construct($cache_dir = 'cache', $default_ttl = 3600) {
        $this->cache_dir = $cache_dir;
        $this->default_ttl = $default_ttl;
        
        // Criar diretório de cache se não existir
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }
    }
    
    /**
     * Gera chave de cache baseada em dados
     */
    private function generateKey($key, $params = []) {
        $hash = md5($key . serialize($params));
        return $this->cache_dir . '/' . $hash . '.cache';
    }
    
    /**
     * Verifica se cache existe e é válido
     */
    public function exists($key, $params = []) {
        $file = $this->generateKey($key, $params);
        
        if (!file_exists($file)) {
            return false;
        }
        
        $data = unserialize(file_get_contents($file));
        if (!$data || !isset($data['expires']) || time() > $data['expires']) {
            unlink($file); // Remove cache expirado
            return false;
        }
        
        return true;
    }
    
    /**
     * Obtém dados do cache
     */
    public function get($key, $params = []) {
        if (!$this->exists($key, $params)) {
            return null;
        }
        
        $file = $this->generateKey($key, $params);
        $data = unserialize(file_get_contents($file));
        return $data['value'];
    }
    
    /**
     * Armazena dados no cache
     */
    public function set($key, $value, $ttl = null, $params = []) {
        $ttl = $ttl ?? $this->default_ttl;
        $file = $this->generateKey($key, $params);
        
        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        return file_put_contents($file, serialize($data)) !== false;
    }
    
    /**
     * Remove item do cache
     */
    public function delete($key, $params = []) {
        $file = $this->generateKey($key, $params);
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }
    
    /**
     * Limpa todo o cache
     */
    public function clear() {
        $files = glob($this->cache_dir . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
        return true;
    }
    
    /**
     * Obtém estatísticas do cache
     */
    public function getStats() {
        $files = glob($this->cache_dir . '/*.cache');
        $total_files = count($files);
        $total_size = 0;
        $expired_files = 0;
        
        foreach ($files as $file) {
            $total_size += filesize($file);
            $data = unserialize(file_get_contents($file));
            if (time() > $data['expires']) {
                $expired_files++;
            }
        }
        
        return [
            'total_files' => $total_files,
            'total_size' => $total_size,
            'expired_files' => $expired_files,
            'cache_dir' => $this->cache_dir
        ];
    }
    
    /**
     * Cache com callback (método mais conveniente)
     */
    public function remember($key, $callback, $ttl = null, $params = []) {
        if ($this->exists($key, $params)) {
            return $this->get($key, $params);
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl, $params);
        return $value;
    }
}

// Instância global do cache
$cache = new Cache();

// Funções helper para cache
function cache_remember($key, $callback, $ttl = null, $params = []) {
    global $cache;
    return $cache->remember($key, $callback, $ttl, $params);
}

function cache_get($key, $params = []) {
    global $cache;
    return $cache->get($key, $params);
}

function cache_set($key, $value, $ttl = null, $params = []) {
    global $cache;
    return $cache->set($key, $value, $ttl, $params);
}

function cache_delete($key, $params = []) {
    global $cache;
    return $cache->delete($key, $params);
}

function cache_clear() {
    global $cache;
    return $cache->clear();
}

function cache_stats() {
    global $cache;
    return $cache->getStats();
}

// Função para invalidar cache relacionado a documentos
function invalidate_document_cache($documento_id = null) {
    // Cache de listagem de documentos
    cache_delete('documentos_lista');
    cache_delete('documentos_estatisticas');
    
    if ($documento_id) {
        cache_delete('documento_' . $documento_id);
    }
}

// Função para invalidar cache de usuários
function invalidate_user_cache($usuario_id = null) {
    cache_delete('usuarios_lista');
    
    if ($usuario_id) {
        cache_delete('usuario_' . $usuario_id);
    }
}
?> 