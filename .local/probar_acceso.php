<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function pedir($ruta, $datos = null) {
    global $cookies;
    $c = curl_init('http://127.0.0.1:8086/' . $ruta);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEFILE => $cookies, CURLOPT_COOKIEJAR => $cookies]);
    if ($datos !== null) { curl_setopt($c, CURLOPT_POST, true); curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($datos)); }
    $respuesta = curl_exec($c);
    if ($respuesta === false) throw new Exception(curl_error($c));
    $estado = curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_setopt($c, CURLOPT_COOKIELIST, 'FLUSH'); curl_close($c);
    return [$estado, $respuesta];
}
function comprobar($condicion, $nombre) { if (!$condicion) throw new Exception($nombre); echo "OK: $nombre\n"; }
function token($html) { preg_match('/name="token" value="([a-f0-9]+)"/', $html, $m); return $m[1] ?? ''; }
$cookies = tempnam(sys_get_temp_dir(), 'schooldefend_http_');
try {
    comprobar(pedir('index.php')[0] === 302, 'Inicio exige sesión');
    [$estado, $html] = pedir('login.php');
    comprobar($estado === 200 && token($html) !== '', 'Login y token');
    [$estado, $respuesta] = pedir('login.php', ['token' => token($html), 'nombre_usuario' => 'admin', 'password' => 'incorrecta']);
    comprobar(str_contains($respuesta, 'Usuario o contraseña incorrectos'), 'Rechaza contraseña incorrecta');
    [$estado, $respuesta] = pedir('login.php', ['nombre_usuario' => 'admin', 'password' => 'DemoEscolar2026!']);
    comprobar(str_contains($respuesta, 'El formulario venció'), 'Rechaza login sin token');
    foreach (['admin' => 'Administrador', 'tecnico' => 'Técnico', 'profesor' => 'Profesor'] as $cuenta => $rol) {
        [, $html] = pedir('login.php');
        comprobar(pedir('login.php', ['token' => token($html), 'nombre_usuario' => $cuenta, 'password' => 'DemoEscolar2026!'])[0] === 302, 'Ingreso ' . $rol);
        [$estado, $inicio] = pedir('index.php');
        comprobar($estado === 200 && str_contains($inicio, $rol), 'Inicio ' . $rol);
        if ($cuenta === 'profesor') {
            comprobar(str_contains($inicio, 'Mis últimos tickets') && !str_contains($inicio, 'Impresora no toma papel') && !str_contains($inicio, 'Incidentes abiertos'), 'Profesor solo ve sus datos');
        } else {
            comprobar(str_contains($inicio, 'Impresora no toma papel') && str_contains($inicio, 'Incidentes abiertos'), 'Resumen técnico general');
        }
        comprobar(pedir('logout.php')[0] === 405, 'GET no cierra sesión');
        comprobar(pedir('logout.php', ['token' => 'incorrecto'])[0] === 403, 'Rechaza cierre sin token válido');
        comprobar(pedir('index.php')[0] === 200, 'Conserva sesión tras petición inválida');
        comprobar(pedir('logout.php', ['token' => token($inicio)])[0] === 302, 'Cierre ' . $rol);
        comprobar(pedir('index.php')[0] === 302, 'Sesión cerrada ' . $rol);
    }
} finally { unlink($cookies); }




