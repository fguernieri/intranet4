<?php
/**
 * Classe de conexão com Supabase usando cURL
 * Adaptada para o módulo de Marketing
 */
class SupabaseConnection {
    private $url;
    private $key;
    private $headers;

    public function __construct() {
        $this->url = 'https://gybhszcefuxsdhpvxbnk.supabase.co';
        $this->key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imd5YmhzemNlZnV4c2RocHZ4Ym5rIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTMwMTYwOTYsImV4cCI6MjA2ODU5MjA5Nn0.i3sOjUvpsimEvtO0uDGFNK92IZjcy1VIva_KEBdlZI8';
        
        $this->headers = [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];
    }

    /**
     * Testa a conexão com Supabase
     */
    public function testConnection() {
        try {
            $ch = curl_init($this->url . '/rest/v1/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 200 || $httpCode === 404;
        } catch (Exception $e) {
            error_log("Erro ao testar conexão Supabase: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Executa SELECT no Supabase
     */
    public function select($table, $options = []) {
        $url = $this->url . '/rest/v1/' . $table;
        
        $params = [];
        if (isset($options['select'])) {
            $params[] = 'select=' . urlencode($options['select']);
        }
        if (isset($options['filters'])) {
            foreach ($options['filters'] as $key => $value) {
                // Se for um array numérico, permite múltiplos filtros com mesma coluna
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $params[] = $key . '=' . urlencode($v);
                    }
                } else {
                    $params[] = $key . '=' . urlencode($value);
                }
            }
        }
        if (isset($options['order'])) {
            $params[] = 'order=' . urlencode($options['order']);
        }
        if (isset($options['limit'])) {
            $params[] = 'limit=' . intval($options['limit']);
        }
        
        if (!empty($params)) {
            $url .= '?' . implode('&', $params);
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        }
        
        error_log("Erro Supabase SELECT: HTTP $httpCode - $response");
        return null;
    }

    /**
     * Executa INSERT no Supabase
     */
    public function insert($table, $data) {
        $url = $this->url . '/rest/v1/' . $table;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 201) {
            return json_decode($response, true);
        }
        
        error_log("Erro Supabase INSERT: HTTP $httpCode - $response");
        return null;
    }
}
