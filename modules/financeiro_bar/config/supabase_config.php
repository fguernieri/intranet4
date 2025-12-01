<?php
/**
 * Configuração do Supabase para módulo financeiro_bar
 * Preencha os dados abaixo com suas credenciais
 */

// Credenciais do Supabase - PREENCHER
$supabase_config = [
    'url' => 'https://gybhszcefuxsdhpvxbnk.supabase.co',  // URL do seu projeto
    'anon_key' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imd5YmhzemNlZnV4c2RocHZ4Ym5rIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTMwMTYwOTYsImV4cCI6MjA2ODU5MjA5Nn0.i3sOjUvpsimEvtO0uDGFNK92IZjcy1VIva_KEBdlZI8',      // Chave pública (anon key)
    'service_key' => '',   // Chave de serviço (opcional)
    'use_service_key' => false,                   // true para usar service_key, false para anon_key
];

// Configurações de timeout
$supabase_timeout = 200; // segundos

// Expor timeout dentro do array de configuração para compatibilidade
$supabase_config['timeout'] = $supabase_timeout;

return $supabase_config;
