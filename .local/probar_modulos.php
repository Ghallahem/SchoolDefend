<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Prueba HTTP con una copia del código y una base temporal, sin tocar schooldefend.
$raiz = dirname(__DIR__);
$sufijo = bin2hex(random_bytes(5));
$nombreBase = 'schooldefend_prueba_' . $sufijo;
$carpetaPrueba = __DIR__ . '/modulos_' . $sufijo;
$urlPrueba = 'http://127.0.0.1:8086/.local/modulos_' . $sufijo . '/';
$cookies = tempnam(sys_get_temp_dir(), 'schooldefend_modulos_');
$pdoPrueba = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$baseCreada = false;

function comprobar($condicion, $descripcion)
{
    if (!$condicion) {
        throw new Exception($descripcion);
    }
    echo 'OK: ' . $descripcion . PHP_EOL;
}

function pedir($ruta, $datos = null)
{
    global $urlPrueba, $cookies;
    $peticion = curl_init($urlPrueba . $ruta);
    curl_setopt_array($peticion, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_COOKIEFILE => $cookies,
        CURLOPT_COOKIEJAR => $cookies
    ]);
    if ($datos !== null) {
        curl_setopt($peticion, CURLOPT_POST, true);
        curl_setopt($peticion, CURLOPT_POSTFIELDS, http_build_query($datos));
    }
    $respuesta = curl_exec($peticion);
    if ($respuesta === false) {
        throw new Exception(curl_error($peticion));
    }
    $estado = curl_getinfo($peticion, CURLINFO_HTTP_CODE);
    curl_setopt($peticion, CURLOPT_COOKIELIST, 'FLUSH');
    curl_close($peticion);
    comprobar(!str_contains($respuesta, '<b>Warning</b>') && !str_contains($respuesta, '<b>Fatal error</b>'), 'Sin errores PHP en ' . $ruta);
    return [$estado, $respuesta];
}

function enviar($ruta, $datos)
{
    [, $formulario] = pedir($ruta);
    preg_match('/name="token" value="([a-f0-9]+)"/', $formulario, $resultado);
    $datos['token'] = $resultado[1] ?? '';
    return pedir($ruta, $datos);
}

function entrar($nombre)
{
    [, $pagina] = pedir('index.php');
    preg_match('/name="token" value="([a-f0-9]+)"/', $pagina, $resultado);
    if (isset($resultado[1])) {
        pedir('logout.php', ['token' => $resultado[1]]);
    }
    comprobar(enviar('login.php', ['nombre_usuario' => $nombre, 'password' => 'DemoEscolar2026!'])[0] === 302, 'Ingreso ' . $nombre);
}

try {
    mkdir($carpetaPrueba);
    foreach (['index.php', 'login.php', 'logout.php'] as $archivo) {
        copy($raiz . '/' . $archivo, $carpetaPrueba . '/' . $archivo);
    }
    foreach (['includes', 'usuarios', 'ubicaciones', 'dispositivos', 'tickets', 'incidentes', 'informes', 'css'] as $carpeta) {
        mkdir($carpetaPrueba . '/' . $carpeta);
        foreach (glob($raiz . '/' . $carpeta . '/*') as $archivo) {
            if (is_file($archivo)) {
                copy($archivo, $carpetaPrueba . '/' . $carpeta . '/' . basename($archivo));
            }
        }
    }
    $conexion = file_get_contents($carpetaPrueba . '/includes/conexion.php');
    file_put_contents($carpetaPrueba . '/includes/conexion.php', str_replace("'schooldefend'", "'" . $nombreBase . "'", $conexion));

    $sql = file_get_contents($raiz . '/database/schooldefend.sql');
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
    $sql = str_replace(['CREATE DATABASE schooldefend ', 'USE schooldefend;'], ['CREATE DATABASE ' . $nombreBase . ' ', 'USE ' . $nombreBase . ';'], $sql);
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $sentencia) {
        if (trim($sentencia) === '') {
            continue;
        }
        $pdoPrueba->exec($sentencia);
        if (str_contains($sentencia, 'CREATE DATABASE ' . $nombreBase)) {
            $baseCreada = true;
        }
    }

    comprobar(pedir('dispositivos/index.php')[0] === 302, 'Inventario exige sesión');
    entrar('profesor');
    foreach (['usuarios/index.php', 'usuarios/formulario.php', 'usuarios/baja.php?id=1', 'ubicaciones/index.php', 'ubicaciones/formulario.php', 'ubicaciones/baja.php?id=1', 'dispositivos/index.php', 'dispositivos/formulario.php', 'dispositivos/baja.php?id=1', 'dispositivos/detalle.php?id=1'] as $ruta) {
        comprobar(pedir($ruta)[0] === 403, 'Profesor sin acceso a ' . $ruta);
    }
    entrar('tecnico');
    comprobar(pedir('dispositivos/index.php')[0] === 200, 'Técnico accede al inventario');
    comprobar(pedir('usuarios/formulario.php')[0] === 403, 'Técnico no administra usuarios');
    comprobar(pedir('ubicaciones/formulario.php')[0] === 403, 'Técnico no administra ubicaciones');
    entrar('admin');
    foreach (['usuarios/index.php', 'ubicaciones/index.php', 'dispositivos/index.php', 'dispositivos/detalle.php?id=5'] as $ruta) {
        comprobar(pedir($ruta)[0] === 200, 'Administrador accede a ' . $ruta);
    }
    comprobar(pedir('dispositivos/formulario.php?id=no')[0] === 404, 'ID inválido no abre alta');
    comprobar(pedir('usuarios/baja.php?id=999999')[0] === 404, 'Registro inexistente');

    $datosUsuario = ['nombre' => 'Cuenta prueba', 'nombre_usuario' => 'prueba', 'rol' => 'profesor', 'password' => 'DemoEscolar2026!'];
    comprobar(enviar('usuarios/formulario.php', $datosUsuario)[0] === 302, 'Alta usuario');
    $idUsuario = $pdoPrueba->query("SELECT id_usuario FROM usuarios WHERE nombre_usuario = 'prueba'")->fetchColumn();
    comprobar(str_contains(enviar('usuarios/formulario.php', $datosUsuario)[1], 'ya existe'), 'Usuario duplicado rechazado');
    $datosUsuario['nombre'] = 'Cuenta editada';
    $datosUsuario['password'] = '';
    comprobar(enviar('usuarios/formulario.php?id=' . $idUsuario, $datosUsuario)[0] === 302, 'Edición usuario sin cambiar contraseña');
    $hash = $pdoPrueba->query('SELECT password_hash FROM usuarios WHERE id_usuario = ' . (int) $idUsuario)->fetchColumn();
    comprobar(password_verify('DemoEscolar2026!', $hash), 'Conserva contraseña');
    comprobar(str_contains(enviar('usuarios/formulario.php?id=1', ['nombre' => 'Administrador Demo', 'nombre_usuario' => 'admin', 'rol' => 'profesor', 'password' => ''])[1], 'al menos un Administrador'), 'No quita último administrador');
    comprobar(str_contains(enviar('usuarios/baja.php?id=1', ['accion' => 'baja', 'motivo' => 'Prueba'])[1], 'propia cuenta'), 'No permite autobaja');
    comprobar(str_contains(enviar('usuarios/baja.php?id=2', ['accion' => 'baja', 'motivo' => 'Prueba'])[1], 'Reasigná primero'), 'No desactiva técnico con pendientes');
    comprobar(str_contains(enviar('usuarios/formulario.php?id=2', ['nombre' => 'Técnico Demo', 'nombre_usuario' => 'tecnico', 'rol' => 'profesor', 'password' => ''])[1], 'Reasigná los tickets'), 'No cambia técnico ocupado a Profesor');

    comprobar(enviar('ubicaciones/formulario.php', ['nombre' => 'Sector prueba', 'descripcion' => '<script>prueba</script>'])[0] === 302, 'Alta ubicación');
    $idUbicacion = $pdoPrueba->query("SELECT id_ubicacion FROM ubicaciones WHERE nombre = 'Sector prueba'")->fetchColumn();
    comprobar(str_contains(pedir('ubicaciones/index.php')[1], '&lt;script&gt;prueba&lt;/script&gt;'), 'Escapa contenido HTML');
    comprobar(str_contains(enviar('ubicaciones/formulario.php', ['nombre' => 'Sector prueba'])[1], 'Ya existe'), 'Nombre ubicación duplicado rechazado');
    comprobar(str_contains(enviar('ubicaciones/baja.php?id=1', ['accion' => 'baja', 'motivo' => 'Prueba'])[1], 'Reubicá los equipos'), 'Bloquea baja de ubicación ocupada');

    $datosEquipo = ['codigo' => 'EQ-PRUEBA', 'tipo' => 'pc', 'id_ubicacion' => $idUbicacion, 'id_responsable' => $idUsuario, 'estado' => 'operativo'];
    comprobar(enviar('dispositivos/formulario.php', $datosEquipo)[0] === 302, 'Alta equipo con campos opcionales vacíos');
    $idEquipo = $pdoPrueba->query("SELECT id_dispositivo FROM dispositivos WHERE codigo = 'EQ-PRUEBA'")->fetchColumn();
    comprobar(str_contains(enviar('dispositivos/formulario.php', $datosEquipo)[1], 'Ese código ya existe'), 'Código duplicado rechazado');
    $datosEquipo['ip'] = '999.0.0.1';
    comprobar(str_contains(enviar('dispositivos/formulario.php?id=' . $idEquipo, $datosEquipo)[1], 'IP no tiene'), 'IP inválida rechazada');
    $datosEquipo['ip'] = '192.0.2.1';
    $datosEquipo['mac'] = 'aa-bb-cc-dd-ee-ff';
    $datosEquipo['modelo'] = 'Modelo actualizado';
    comprobar(enviar('dispositivos/formulario.php?id=' . $idEquipo, $datosEquipo)[0] === 302, 'Edición de equipo');
    comprobar($pdoPrueba->query('SELECT mac FROM dispositivos WHERE id_dispositivo = ' . (int) $idEquipo)->fetchColumn() === 'AA:BB:CC:DD:EE:FF', 'Normaliza MAC');
    comprobar(str_contains(pedir('dispositivos/index.php?buscar=EQ-PRUEBA')[1], 'Modelo actualizado'), 'Buscador de inventario');
    comprobar(str_contains(enviar('dispositivos/baja.php?id=2', ['accion' => 'baja', 'motivo' => 'Prueba'])[1], 'pendientes'), 'No da de baja equipo con trabajos pendientes');

    comprobar(str_contains(enviar('dispositivos/baja.php?id=' . $idEquipo, ['accion' => 'baja', 'motivo' => ''])[1], 'motivo de baja'), 'Baja exige motivo');
    pedir('dispositivos/baja.php?id=' . $idEquipo, ['accion' => 'baja', 'motivo' => 'Prueba']);
    comprobar((int) $pdoPrueba->query('SELECT activo FROM dispositivos WHERE id_dispositivo = ' . (int) $idEquipo)->fetchColumn() === 1, 'POST sin token no modifica');
    comprobar(enviar('dispositivos/baja.php?id=' . $idEquipo, ['accion' => 'baja', 'motivo' => 'Retiro de prueba'])[0] === 302, 'Baja lógica equipo');
    comprobar(str_contains(pedir('dispositivos/index.php?activo=0')[1], 'EQ-PRUEBA'), 'Filtro de inactivos');
    comprobar(enviar('usuarios/baja.php?id=' . $idUsuario, ['accion' => 'baja', 'motivo' => 'Prueba'])[0] === 302, 'Baja usuario sin tareas activas');
    comprobar(enviar('ubicaciones/baja.php?id=' . $idUbicacion, ['accion' => 'baja', 'motivo' => 'Prueba'])[0] === 302, 'Baja ubicación sin equipos activos');
    comprobar(str_contains(enviar('dispositivos/baja.php?id=' . $idEquipo, ['accion' => 'reactivar'])[1], 'inactivo'), 'Reactivación rechaza responsable inactivo');
    comprobar(enviar('usuarios/baja.php?id=' . $idUsuario, ['accion' => 'reactivar'])[0] === 302, 'Reactivar usuario');
    comprobar(str_contains(enviar('dispositivos/baja.php?id=' . $idEquipo, ['accion' => 'reactivar'])[1], 'ubicación activa'), 'Reactivación rechaza ubicación inactiva');
    comprobar(enviar('ubicaciones/baja.php?id=' . $idUbicacion, ['accion' => 'reactivar'])[0] === 302, 'Reactivar ubicación');
    comprobar(enviar('dispositivos/baja.php?id=' . $idEquipo, ['accion' => 'reactivar'])[0] === 302, 'Reactivar equipo');
    comprobar($pdoPrueba->query('SELECT motivo_baja FROM dispositivos WHERE id_dispositivo = ' . (int) $idEquipo)->fetchColumn() === 'Retiro de prueba', 'Reactivación conserva datos de última baja');
    comprobar(str_contains(pedir('dispositivos/detalle.php?id=5')[1], 'Equipo antiguo no enciende'), 'Equipo inactivo conserva historial');
    // Tickets: probar el flujo completo y los límites de cada rol.
    entrar('profesor');
    comprobar(pedir('tickets/index.php')[0] === 200, 'Profesor accede a sus tickets');
    comprobar(!str_contains(pedir('tickets/index.php')[1], 'Impresora no toma papel'), 'Listado no muestra reportes ajenos');
    comprobar(pedir('tickets/detalle.php?id=3')[0] === 404, 'No revela detalle ajeno');
    comprobar(pedir('tickets/editar.php?id=1')[0] === 403, 'Profesor no gestiona tickets');
    $datosTicket = ['titulo' => 'Reporte prueba', 'descripcion' => '<b>Problema general</b>', 'id_ubicacion' => 2];
    comprobar(enviar('tickets/insertar.php', $datosTicket + ['id_creador' => 1, 'id_tecnico' => 1, 'prioridad' => 'alta', 'estado' => 'resuelto'])[0] === 302, 'Profesor crea ticket general');
    $idTicket = (int) $pdoPrueba->query("SELECT id_ticket FROM tickets WHERE titulo = 'Reporte prueba'")->fetchColumn();
    $ticketGuardado = $pdoPrueba->query('SELECT * FROM tickets WHERE id_ticket = ' . $idTicket)->fetch(PDO::FETCH_ASSOC);
    comprobar((int) $ticketGuardado['id_creador'] === 3 && $ticketGuardado['id_tecnico'] === null && $ticketGuardado['id_dispositivo'] === null && $ticketGuardado['prioridad'] === 'media' && $ticketGuardado['estado'] === 'abierto', 'Servidor fija creador, prioridad y estado');
    comprobar(str_contains(pedir('tickets/detalle.php?id=' . $idTicket)[1], '&lt;b&gt;Problema general&lt;/b&gt;'), 'Detalle escapa reporte');
    comprobar(str_contains(enviar('tickets/insertar.php', $datosTicket + ['id_dispositivo' => 1])[1], 'pertenecer a la ubicación'), 'Rechaza equipo de otro sector');
    $datosEquipoTicket = ['titulo' => 'Equipo prueba', 'descripcion' => 'No enciende', 'id_ubicacion' => 1, 'id_dispositivo' => 5];
    comprobar(str_contains(enviar('tickets/insertar.php', $datosEquipoTicket)[1], 'activo'), 'Rechaza equipo dado de baja');
    $datosEquipoTicket['id_dispositivo'] = 1;
    comprobar(enviar('tickets/insertar.php', $datosEquipoTicket)[0] === 302, 'Reporte con equipo activo');
    $cantidadAntes = (int) $pdoPrueba->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
    pedir('tickets/insertar.php', $datosTicket);
    comprobar((int) $pdoPrueba->query('SELECT COUNT(*) FROM tickets')->fetchColumn() === $cantidadAntes, 'Crear sin token no guarda');

    entrar('tecnico');
    $datosGestion = ['id_tecnico' => 2, 'estado' => 'en_proceso', 'prioridad' => 'alta', 'novedad' => 'Se inicia la revisión.'];
    comprobar(enviar('tickets/editar.php?id=' . $idTicket, $datosGestion)[0] === 302, 'Técnico toma ticket y cambia prioridad');
    $datosGestion['id_tecnico'] = 3;
    comprobar(str_contains(enviar('tickets/editar.php?id=' . $idTicket, $datosGestion)[1], 'Administrador activo'), 'No asigna a Profesor');
    $datosGestion['id_tecnico'] = 2;
    $datosGestion['estado'] = 'en_espera_repuesto';
    $datosGestion['novedad'] = '';
    comprobar(str_contains(enviar('tickets/editar.php?id=' . $idTicket, $datosGestion)[1], 'qué repuesto falta'), 'Espera exige explicación');
    $datosGestion['novedad'] = 'Falta un cable de red.';
    comprobar(enviar('tickets/editar.php?id=' . $idTicket, $datosGestion)[0] === 302, 'Espera de repuesto');
    $datosGestion['estado'] = 'en_proceso';
    $datosGestion['novedad'] = 'Llegó el cable.';
    comprobar(enviar('tickets/editar.php?id=' . $idTicket, $datosGestion)[0] === 302, 'Retoma atención');
    $datosGestion['estado'] = 'resuelto';
    comprobar(str_contains(enviar('tickets/editar.php?id=' . $idTicket, $datosGestion)[1], 'diagnóstico y la solución'), 'Resolver exige diagnóstico y solución');
    $datosGestion['diagnostico'] = 'Cable dañado.';
    $datosGestion['solucion'] = 'Se reemplazó y se comprobó la conexión.';
    comprobar(enviar('tickets/editar.php?id=' . $idTicket, $datosGestion)[0] === 302, 'Resolución del ticket');
    comprobar($pdoPrueba->query('SELECT fecha_cierre FROM tickets WHERE id_ticket = ' . $idTicket)->fetchColumn() !== null, 'Resolución guarda fecha');
    comprobar(pedir('tickets/editar.php?id=' . $idTicket)[0] === 409, 'Finalizados no se reabren ni editan');
    comprobar((int) $pdoPrueba->query('SELECT COUNT(*) FROM seguimiento_tickets WHERE id_ticket = ' . $idTicket)->fetchColumn() === 5, 'Historial conserva cinco pasos y no intentos inválidos');
    $datosCancelar = ['id_tecnico' => '', 'prioridad' => 'media', 'estado' => 'cancelado'];
    comprobar(str_contains(enviar('tickets/editar.php?id=1', $datosCancelar)[1], 'indicá el motivo'), 'Cancelar exige motivo');
    $datosCancelar['motivo_cancelacion'] = 'Reporte duplicado.';
    comprobar(enviar('tickets/editar.php?id=1', $datosCancelar)[0] === 302, 'Cancelación conserva ticket');
    entrar('profesor');
    $detallePropio = pedir('tickets/detalle.php?id=' . $idTicket)[1];
    comprobar(str_contains($detallePropio, 'Cable dañado.') && str_contains($detallePropio, 'Falta un cable'), 'Reportante consulta solución e historial');
    comprobar(str_contains(pedir('tickets/index.php?estado=resuelto&buscar=Reporte')[1], 'Reporte prueba'), 'Filtros por estado y búsqueda');
    entrar('admin');
    // Incidentes: permisos y ciclo manual de registro, investigación y cierre.
    comprobar(pedir('incidentes/index.php')[0] === 200, 'Administrador consulta incidentes');
    entrar('profesor');
    foreach (['incidentes/index.php', 'incidentes/formulario.php', 'incidentes/detalle.php?id=1'] as $rutaIncidente) {
        comprobar(pedir($rutaIncidente)[0] === 403, 'Profesor sin acceso a ' . $rutaIncidente);
    }
    entrar('tecnico');
    $datosIncidente = [
        'titulo' => 'Incidente prueba', 'tipo' => 'phishing', 'descripcion' => '<b>Correo sospechoso</b>',
        'severidad' => 'alta', 'id_responsable' => 2
    ];
    comprobar(str_contains(pedir('incidentes/formulario.php', $datosIncidente)[1], 'venció'), 'Incidentes exigen token');
    comprobar(enviar('incidentes/formulario.php', $datosIncidente + ['estado' => 'cerrado', 'id_creador' => 1])[0] === 302, 'Crear incidente sin equipo ni ticket');
    $idIncidente = (int) $pdoPrueba->query("SELECT id_incidente FROM incidentes WHERE titulo = 'Incidente prueba'")->fetchColumn();
    $incidenteGuardado = $pdoPrueba->query('SELECT * FROM incidentes WHERE id_incidente = ' . $idIncidente)->fetch(PDO::FETCH_ASSOC);
    comprobar($incidenteGuardado['estado'] === 'reportado' && (int) $incidenteGuardado['id_creador'] === 2, 'Servidor fija estado inicial y creador');
    comprobar(str_contains(pedir('incidentes/detalle.php?id=' . $idIncidente)[1], '&lt;b&gt;Correo sospechoso&lt;/b&gt;'), 'Detalle escapa descripción');
    comprobar(str_contains(enviar('incidentes/formulario.php', array_replace($datosIncidente, ['id_responsable' => 3]))[1], 'Administrador activo'), 'Incidente no se asigna a Profesor');
    comprobar(str_contains(enviar('incidentes/formulario.php', $datosIncidente + ['id_dispositivo' => 5])[1], 'equipo activo'), 'No vincula equipo inactivo');
    comprobar(str_contains(enviar('incidentes/formulario.php', $datosIncidente + ['id_dispositivo' => 1, 'id_ticket' => 2])[1], 'no coincide'), 'Valida equipo del ticket');
    comprobar(str_contains(enviar('incidentes/formulario.php', $datosIncidente + ['id_ticket' => 999999])[1], 'no existe'), 'Valida ticket inexistente');
    comprobar(enviar('incidentes/formulario.php', array_replace($datosIncidente, ['titulo' => 'Incidente vinculado', 'id_dispositivo' => 2, 'id_ticket' => 2]))[0] === 302, 'Crea incidente vinculado');
    $rutaGestionIncidente = 'incidentes/formulario.php?id=' . $idIncidente;
    $datosAtencion = ['id_responsable' => 2, 'severidad' => 'media', 'estado' => 'en_investigacion', 'acciones_realizadas' => 'Se revisó el correo.'];
    comprobar(enviar($rutaGestionIncidente, $datosAtencion + ['titulo' => 'Alterado', 'id_ticket' => 3])[0] === 302, 'Inicia investigación');
    comprobar($pdoPrueba->query('SELECT titulo FROM incidentes WHERE id_incidente = ' . $idIncidente)->fetchColumn() === 'Incidente prueba', 'Gestionar conserva reporte original');
    comprobar(str_contains(enviar($rutaGestionIncidente, array_replace($datosAtencion, ['estado' => 'reportado']))[1], 'no vuelve'), 'No retrocede desde investigación');
    $datosAtencion['estado'] = 'cerrado';
    comprobar(str_contains(enviar($rutaGestionIncidente, $datosAtencion)[1], 'conclusión'), 'Cerrar exige conclusión');
    $datosAtencion['conclusion'] = 'Intento de phishing descartado sin abrir enlaces.';
    comprobar(str_contains(enviar($rutaGestionIncidente, array_replace($datosAtencion, ['acciones_realizadas' => '']))[1], 'acciones'), 'Cerrar exige acciones');
    comprobar(enviar($rutaGestionIncidente, $datosAtencion)[0] === 302, 'Cierra incidente');
    comprobar($pdoPrueba->query('SELECT fecha_cierre FROM incidentes WHERE id_incidente = ' . $idIncidente)->fetchColumn() !== null, 'Cierre guarda fecha');
    comprobar(pedir($rutaGestionIncidente, $datosAtencion)[0] === 409, 'No edita incidente cerrado');
    comprobar(str_contains(pedir('incidentes/index.php?estado=cerrado&buscar=Incidente+prueba')[1], 'Incidente prueba'), 'Filtros permiten consultar cerrado');
    comprobar(pedir('incidentes/detalle.php?id=999999')[0] === 404, 'Incidente inexistente responde 404');
    comprobar(str_contains(pedir('informes/index.php?tipo=inventario&activo=0')[1], 'PC-LAB-03'), 'Informe conserva equipos dados de baja');
    comprobar(!str_contains(pedir('informes/index.php?tipo=inventario&activo=1')[1], 'PC-LAB-03'), 'Informe filtra equipos activos');
    comprobar(str_contains(pedir('informes/index.php?tipo=tickets&desde=2026-09-10&hasta=2026-09-10')[1], 'Proyector sin imagen'), 'Informe incluye el día final completo');
    comprobar(!str_contains(pedir('informes/index.php?tipo=tickets&desde=2026-09-10&hasta=2026-09-10')[1], 'Ventanas extrañas'), 'Informe excluye fechas fuera del rango');
    comprobar(str_contains(pedir('informes/index.php?tipo=tickets&desde=2026-02-30')[1], 'fechas válidas'), 'Informe rechaza fecha imposible');
    comprobar(str_contains(pedir('informes/index.php?tipo=tickets&desde=2026-10-01&hasta=2026-09-01')[1], 'fecha inicial'), 'Informe rechaza período invertido');
    comprobar(str_contains(pedir('informes/index.php?tipo=incidentes&estado=cerrado')[1], 'Incidente prueba'), 'Informe de incidentes cerrados');
    comprobar(str_contains(pedir('informes/index.php?tipo=tickets&estado=inventado')[1], 'estado válido'), 'Informe valida estado');
    entrar('profesor');
    comprobar(pedir('informes/index.php')[0] === 403, 'Profesor no accede a informes generales');
    entrar('admin');
    comprobar(pedir('informes/index.php')[0] === 200, 'Administrador accede a informes');
    echo 'RESULTADO: pruebas completadas sin modificar la base definitiva.' . PHP_EOL;
} finally {
    if ($pdoPrueba->inTransaction()) {
        $pdoPrueba->rollBack();
    }
    if ($baseCreada) {
        $pdoPrueba->exec('DROP DATABASE `' . $nombreBase . '`');
    }
    unlink($cookies);
    // Solo retirar los archivos de la copia creada por esta ejecución.
    $rutaReal = realpath($carpetaPrueba);
    $raizLocal = realpath(__DIR__) . DIRECTORY_SEPARATOR;
    if ($rutaReal !== false && str_starts_with($rutaReal, $raizLocal) && basename($rutaReal) === 'modulos_' . $sufijo) {
        foreach (['includes', 'usuarios', 'ubicaciones', 'dispositivos', 'tickets', 'incidentes', 'informes', 'css'] as $carpeta) {
            foreach (glob($rutaReal . '/' . $carpeta . '/*') as $archivo) {
                unlink($archivo);
            }
            rmdir($rutaReal . '/' . $carpeta);
        }
        foreach (['index.php', 'login.php', 'logout.php'] as $archivo) {
            unlink($rutaReal . '/' . $archivo);
        }
        rmdir($rutaReal);
    }
}

