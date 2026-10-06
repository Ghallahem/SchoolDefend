<?php
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/../tickets/opciones.php';
require_once __DIR__ . '/../incidentes/opciones.php';
require_once __DIR__ . '/../dispositivos/opciones.php';

$usuario = exigirSesion('../login.php');
exigirRoles($usuario, ['administrador', 'tecnico']);
$pdo = getConexion();
$rutaBase = '../';
$titulo = 'Informes';
$tiposInforme = ['tickets' => 'Tickets', 'incidentes' => 'Incidentes', 'inventario' => 'Inventario'];
$tipoInforme = textoEntrada($_GET, 'tipo', 'tickets');
if (!isset($tiposInforme[$tipoInforme])) {
    $tipoInforme = 'tickets';
}
$desde = textoEntrada($_GET, 'desde');
$hasta = textoEntrada($_GET, 'hasta');
$estado = textoEntrada($_GET, 'estado');
$activo = textoEntrada($_GET, 'activo', 'todos');
$mensaje = '';
$parametros = [];
$filas = [];
$fechaEmision = date('d/m/Y H:i');

if ($tipoInforme === 'inventario') {
    $opcionesEstado = $estadosDispositivo;
    $columnas = ['Código', 'Tipo', 'Ubicación', 'Responsable', 'Estado', 'Registro'];
    $sqlInforme = "
        SELECT dispositivos.codigo, dispositivos.tipo, ubicaciones.nombre AS ubicacion,
               usuarios.nombre AS responsable, dispositivos.estado, dispositivos.activo
        FROM dispositivos
        INNER JOIN ubicaciones ON dispositivos.id_ubicacion = ubicaciones.id_ubicacion
        LEFT JOIN usuarios ON dispositivos.id_responsable = usuarios.id_usuario
        WHERE 1 = 1";
    if (!in_array($activo, ['todos', '1', '0'], true)) {
        $mensaje = 'Elegí un estado de registro válido.';
    } elseif ($activo !== 'todos') {
        $sqlInforme .= ' AND dispositivos.activo = ?';
        $parametros[] = $activo;
    }
    $campoEstado = 'dispositivos.estado';
    $orden = ' ORDER BY dispositivos.codigo';
    $descripcionPeriodo = 'Inventario actual. Las fechas no se aplican a este informe.';
} else {
    $opcionesEstado = $tipoInforme === 'tickets' ? $estadosTicket : $estadosIncidente;
    if ($tipoInforme === 'tickets') {
        $columnas = ['N.º', 'Título', 'Ubicación', 'Responsable', 'Estado', 'Creado'];
        $sqlInforme = "
            SELECT tickets.id_ticket, tickets.titulo, ubicaciones.nombre AS ubicacion,
                   usuarios.nombre AS responsable, tickets.estado, tickets.fecha_creacion
            FROM tickets
            INNER JOIN ubicaciones ON tickets.id_ubicacion = ubicaciones.id_ubicacion
            LEFT JOIN usuarios ON tickets.id_tecnico = usuarios.id_usuario
            WHERE 1 = 1";
        $campoEstado = 'tickets.estado';
        $campoFecha = 'tickets.fecha_creacion';
        $orden = ' ORDER BY tickets.fecha_creacion DESC, tickets.id_ticket DESC';
    } else {
        $columnas = ['N.º', 'Título', 'Severidad', 'Responsable', 'Estado', 'Creado'];
        $sqlInforme = "
            SELECT incidentes.id_incidente, incidentes.titulo, incidentes.severidad,
                   usuarios.nombre AS responsable, incidentes.estado, incidentes.fecha_creacion
            FROM incidentes
            INNER JOIN usuarios ON incidentes.id_responsable = usuarios.id_usuario
            WHERE 1 = 1";
        $campoEstado = 'incidentes.estado';
        $campoFecha = 'incidentes.fecha_creacion';
        $orden = ' ORDER BY incidentes.fecha_creacion DESC, incidentes.id_incidente DESC';
    }
    foreach ([$desde, $hasta] as $fechaFiltro) {
        if ($fechaFiltro !== '') {
            $fechaValidada = DateTime::createFromFormat('!Y-m-d', $fechaFiltro);
            if (!$fechaValidada || $fechaValidada->format('Y-m-d') !== $fechaFiltro) {
                $mensaje = 'Ingresá fechas válidas.';
            }
        }
    }
    if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
        $mensaje = 'La fecha inicial no puede superar a la final.';
    }
    if ($mensaje === '') {
        if ($desde !== '') {
            $sqlInforme .= ' AND ' . $campoFecha . ' >= ?';
            $parametros[] = $desde . ' 00:00:00';
        }
        if ($hasta !== '') {
            $sqlInforme .= ' AND ' . $campoFecha . ' <= ?';
            $parametros[] = $hasta . ' 23:59:59';
        }
    }
    $descripcionPeriodo = 'Fecha de creación: desde ' . ($desde ?: 'el primer registro') . ' hasta ' . ($hasta ?: 'el último registro') . '.';
}

if ($estado !== '' && !isset($opcionesEstado[$estado])) {
    $mensaje = 'Elegí un estado válido para este informe.';
} elseif ($estado !== '') {
    $sqlInforme .= ' AND ' . $campoEstado . ' = ?';
    $parametros[] = $estado;
}
if ($mensaje === '') {
    $consultaInforme = $pdo->prepare($sqlInforme . $orden);
    $consultaInforme->execute($parametros);
    foreach ($consultaInforme->fetchAll() as $registro) {
        if ($tipoInforme === 'inventario') {
            $filas[] = [
                $registro['codigo'], $tiposDispositivo[$registro['tipo']], $registro['ubicacion'],
                $registro['responsable'] ?? 'Sin asignar', $opcionesEstado[$registro['estado']],
                $registro['activo'] ? 'Activo' : 'Dado de baja'
            ];
        } else {
            $identificador = $tipoInforme === 'tickets' ? $registro['id_ticket'] : $registro['id_incidente'];
            $datoExtra = $tipoInforme === 'tickets' ? $registro['ubicacion'] : $severidadesIncidente[$registro['severidad']];
            $filas[] = [
                $identificador, $registro['titulo'], $datoExtra, $registro['responsable'] ?? 'Sin asignar',
                $opcionesEstado[$registro['estado']], $registro['fecha_creacion']
            ];
        }
    }
}
$cantidadRegistros = count($filas);
$estadoMostrado = $opcionesEstado[$estado] ?? 'Todos';
$registroMostrado = $activo === 'todos' ? 'Todos' : ($activo === '1' ? 'Activos' : 'Dados de baja');
require_once __DIR__ . '/../includes/encabezado.php';
?>
<div class="controles-informe">
    <h1>Informes</h1>
    <form method="get" class="filtros">
        <label for="tipo">Informe</label>
        <select id="tipo" name="tipo">
            <?php foreach ($tiposInforme as $valorTipo => $nombreTipo): ?>
                <option value="<?= escapar($valorTipo) ?>" <?= $tipoInforme === $valorTipo ? 'selected' : '' ?>><?= escapar($nombreTipo) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="boton" type="submit">Elegir informe</button>
    </form>
    <form method="get" class="filtros">
        <input type="hidden" name="tipo" value="<?= escapar($tipoInforme) ?>">
        <label for="estado">Estado</label>
        <select id="estado" name="estado">
            <option value="">Todos</option>
            <?php foreach ($opcionesEstado as $valorEstado => $nombreEstado): ?>
                <option value="<?= escapar($valorEstado) ?>" <?= $estado === $valorEstado ? 'selected' : '' ?>><?= escapar($nombreEstado) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($tipoInforme === 'inventario'): ?>
            <label for="activo">Registro</label>
            <select id="activo" name="activo">
                <option value="todos" <?= $activo === 'todos' ? 'selected' : '' ?>>Todos</option>
                <option value="1" <?= $activo === '1' ? 'selected' : '' ?>>Activos</option>
                <option value="0" <?= $activo === '0' ? 'selected' : '' ?>>Dados de baja</option>
            </select>
        <?php else: ?>
            <label for="desde">Desde</label>
            <input type="date" id="desde" name="desde" value="<?= escapar($desde) ?>">
            <label for="hasta">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="<?= escapar($hasta) ?>">
        <?php endif; ?>
        <button class="boton" type="submit">Aplicar filtros</button>
    </form>
    <p class="ayuda">Para guardar el informe, presioná Ctrl + P y elegí Guardar como PDF. Usá orientación horizontal para las tablas.</p>
</div>
<?php if ($mensaje !== ''): ?>
    <p class="alerta" role="alert"><?= escapar($mensaje) ?></p>
<?php else: ?>
    <section class="informe">
        <div class="encabezado-informe">
            <div>
                <h2>Informe de <?= escapar($tiposInforme[$tipoInforme]) ?></h2>
                <p class="institucion-informe">ENS N.º 10 · Gestión IT escolar</p>
            </div>
            <img class="logo-informe" src="<?= escapar($rutaBase . 'Imagenes/logo-completo.png') ?>" alt="SchoolDefend">
        </div>
        <p>Emitido: <?= escapar($fechaEmision) ?> · Por: <?= escapar($usuario['nombre']) ?></p>
        <p><?= escapar($descripcionPeriodo) ?> Estado: <?= escapar($estadoMostrado) ?>.</p>
        <?php if ($tipoInforme === 'inventario'): ?>
            <p>Registros: <?= escapar($registroMostrado) ?>.</p>
        <?php endif; ?>
        <p>Total de registros: <?= $cantidadRegistros ?></p>
        <?php if (!$filas): ?>
            <p>No hay registros para los filtros seleccionados.</p>
        <?php else: ?>
            <div class="tabla-contenedor">
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($columnas as $columna): ?>
                                <th scope="col"><?= escapar($columna) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filas as $fila): ?>
                            <tr>
                                <?php foreach ($fila as $valor): ?>
                                    <td><?= escapar($valor) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/pie.php'; ?>
