<?php
function escapar($texto)
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function nombreRol($rol)
{
    $nombresRoles = [
        'administrador' => 'Administrador',
        'tecnico' => 'Técnico',
        'profesor' => 'Profesor'
    ];

    return $nombresRoles[$rol] ?? 'Sin rol';
}

function tokenFormulario()
{
    if (empty($_SESSION['token'])) {
        $_SESSION['token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['token'];
}

function tokenValido()
{
    return isset($_SESSION['token'], $_POST['token'])
        && is_string($_POST['token'])
        && hash_equals($_SESSION['token'], $_POST['token']);
}

// Leer formularios sin aceptar arrays donde se espera texto o un identificador.
function textoEntrada($datos, $campo, $valorInicial = '')
{
    if (!isset($datos[$campo]) || !is_string($datos[$campo])) {
        return $valorInicial;
    }

    return trim($datos[$campo]);
}

function idEntrada($datos, $campo)
{
    $valor = textoEntrada($datos, $campo);
    return filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
}

function errorGuardado($error, $mensajeDuplicado)
{
    if ((int) ($error->errorInfo[1] ?? 0) === 1062) {
        return $mensajeDuplicado;
    }

    return 'No se pudo guardar. Revisá los datos e intentá nuevamente.';
}

