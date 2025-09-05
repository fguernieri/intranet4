<?php
// Copy this file to modules/bar_orders/config.php and fill the values.
// Do NOT commit real keys to your repository.
define('SUPABASE_URL', 'https://gybhszcefuxsdhpvxbnk.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imd5YmhzemNlZnV4c2RocHZ4Ym5rIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTMwMTYwOTYsImV4cCI6MjA2ODU5MjA5Nn0.i3sOjUvpsimEvtO0uDGFNK92IZjcy1VIva_KEBdlZI8');

// Name of the orders table in Supabase
define('SUPABASE_ORDERS_TABLE', 'orders');

// Optional: override timezone
date_default_timezone_set('America/Sao_Paulo');

return true;