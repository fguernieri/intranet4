<?php
$tempoSessao = 10800; // 3 horas inativas

ini_set('session.gc_maxlifetime', $tempoSessao);
ini_set('session.cookie_lifetime', $tempoSessao);
session_set_cookie_params($tempoSessao);
