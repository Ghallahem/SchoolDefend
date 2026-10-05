<?php
$estadosTicket = [
    'abierto' => 'Abierto',
    'en_proceso' => 'En proceso',
    'en_espera_repuesto' => 'En espera de repuesto',
    'resuelto' => 'Resuelto',
    'cancelado' => 'Cancelado'
];

$prioridadesTicket = ['baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta'];

$transicionesTicket = [
    'abierto' => ['abierto', 'en_proceso', 'cancelado'],
    'en_proceso' => ['en_proceso', 'en_espera_repuesto', 'resuelto', 'cancelado'],
    'en_espera_repuesto' => ['en_espera_repuesto', 'en_proceso', 'cancelado'],
    'resuelto' => [],
    'cancelado' => []
];
