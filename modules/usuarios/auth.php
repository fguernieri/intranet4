<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /intranet/login.php');
    exit;
}