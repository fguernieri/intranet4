<?php
/**
 * Classe para conexão e operações com Supabase
 */
class SupabaseConnection {
    private $url;
    private $headers;
    private $timeout;
    
    public function __construct() {
        // Carrega configurações
        $config = require_once __DIR__ . '/config/supabase_config.php';
        
        $this->url = rtrim($config['url'], '/');
        $this->timeout = $config['timeout'] ?? 90;
        
        // Configura headers com a chave apropriada
        $api_key = $config['use_service_key'] ? $config['service_key'] : $config['anon_key'];
        
        $this->headers = [
            'Content-Type: application/json',
            'apikey: ' . $api_key,
            'Authorization: Bearer ' . $api_key,
            'Prefer: return=representation'
        ];
    }
    
    /**
     * Executa requisição GET para Supabase
     */
    public function select($table, $params = []) {
        $query_params = [];
        
        if (isset($params['select'])) {
            $query_params['select'] = $params['select'];
        }
        
        if (isset($params['filters'])) {
            foreach ($params['filters'] as $key => $value) {
                $query_params[$key] = $value;
            }
        }
        
        if (isset($params['order'])) {
            $query_params['order'] = $params['order'];
        }
        
        if (isset($params['limit'])) {
            $query_params['limit'] = $params['limit'];
        }
        
        if (isset($params['offset'])) {
            $query_params['offset'] = $params['offset'];
        }
        
        $url = $this->url . '/rest/v1/' . $table;
        if (!empty($query_params)) {
            $url .= '?' . http_build_query($query_params);
        }
        
        return $this->makeRequest('GET', $url);
    }
    
    /**
     * Executa requisição POST para Supabase (INSERT)
     */
    public function insert($table, $data) {
        $url = $this->url . '/rest/v1/' . $table;
        return $this->makeRequest('POST', $url, $data);
    }
    
    /**
     * Executa requisição PATCH para Supabase (UPDATE)
     */
    public function update($table, $data, $filters = []) {
        $url = $this->url . '/rest/v1/' . $table;
        
        if (!empty($filters)) {
            $url .= '?' . http_build_query($filters);
        }
        
        return $this->makeRequest('PATCH', $url, $data);
    }
    
    /**
     * Executa requisição DELETE para Supabase
     */
    public function delete($table, $filters = []) {
        $url = $this->url . '/rest/v1/' . $table;
        
        if (!empty($filters)) {
            $url .= '?' . http_build_query($filters);
        }
        
        return $this->makeRequest('DELETE', $url);
    }
    
    /**
     * Faz a requisição HTTP
     */
    private function makeRequest($method, $url, $data = null) {
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $this->headers,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        
        if ($data !== null && in_array($method, ['POST', 'PATCH'])) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        
        curl_close($curl);
        
        if ($error) {
            error_log("Erro cURL Supabase: " . $error);
            return false;
        }
        
        if ($http_code >= 400) {
            error_log("Erro HTTP Supabase: " . $http_code . " - " . $response);
            return false;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Testa a conexão com Supabase
     */
    public function testConnection() {
        try {
            $result = $this->makeRequest('GET', $this->url . '/rest/v1/');
            return $result !== false;
        } catch (Exception $e) {
            error_log("Erro ao testar conexão Supabase: " . $e->getMessage());
            return false;
        }
    }
}