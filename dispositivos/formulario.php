<?php
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/opciones.php';

$usuario = exigirSesion('../login.php');
exigirRoles($usuario, ['administrador', 'tecnico']);
$pdo = getConexion();
$rutaBase = '../';
$idDispositivo = idEntrada($_GET, 'id');
$esEdicion = isset($_GET['id']);
$titulo = $esEdicion ? 'Editar dispositivo' : 'Nuevo dispositivo';
$mensaje = '';
$dispositivo = [
    'codigo' => '', 'tipo' => 'pc', 'marca' => '', 'modelo' => '',
    'numero_serie' => '', 'sistema_operativo' => '', 'ip' => '', 'mac' => '',
    'id_ubicacion' => '', 'id_responsable' => '', 'estado' => 'operativo',
    'observaciones' => '', 'activo' => 1
];

if ($esEdicion) {
    $consultaDispositivo = $pdo->prepare('SELECT * FROM dispositivos WHERE id_dispositivo = ?');
    $consultaDispositivo->execute([$idDispositivo]);
    $dispositivo = $consultaDispositivo->fetch();

    if (!$dispositivo) {
        http_response_code(404);
        exit('El dispositivo no existe.');
    }
}

// Estas listas pequeñas solo describen los campos de texto del formulario.
$camposTexto = [
    'codigo' => ['etiqueta' => 'Código institucional', 'limite' => 30],
    'marca' => ['etiqueta' => 'Marca', 'limite' => 60],
    'modelo' => ['etiqueta' => 'Modelo', 'limite' => 100],
    'numero_serie' => ['etiqueta' => 'Número de serie', 'limite' => 100],
    'sistema_operativo' => ['etiqueta' => 'Sistema operativo', 'limite' => 100],
    'ip' => ['etiqueta' => 'IP', 'limite' => 45],
    'mac' => ['etiqueta' => 'MAC', 'limite' => 17]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($camposTexto as $campo => $configuracion) {
        $dispositivo[$campo] = textoEntrada($_POST, $campo);

        if (mb_strlen($dispositivo[$campo]) > $configuracion['limite']) {
            $mensaje = 'Revisá la longitud del campo ' . $configuracion['etiqueta'] . '.';
        }
    }

    $dispositivo['tipo'] = textoEntrada($_POST, 'tipo');
    $dispositivo['estado'] = textoEntrada($_POST, 'estado');
    $dispositivo['id_ubicacion'] = idEntrada($_POST, 'id_ubicacion');
    $dispositivo['id_responsable'] = idEntrada($_POST, 'id_responsable');
    $dispositivo['observaciones'] = textoEntrada($_POST, 'observaciones');
    $dispositivo['mac'] = strtoupper(str_replace('-', ':', $dispositivo['mac']));

    if (!tokenValido()) {
        $mensaje = 'El formulario venció. Intentá nuevamente.';
    } elseif ($dispositivo['codigo'] === '' || !$dispositivo['id_ubicacion']) {
        $mensaje = 'Completá el código y seleccioná una ubicación.';
    } elseif (!isset($tiposDispositivo[$dispositivo['tipo']], $estadosDispositivo[$dispositivo['estado']])) {
        $mensaje = 'Seleccioná un tipo y un estado válidos.';
    } elseif (textoEntrada($_POST, 'id_responsable') !== '' && !$dispositivo['id_responsable']) {
        $mensaje = 'El responsable seleccionado no es válido.';
    } elseif ($dispositivo['ip'] !== '' && !filter_var($dispositivo['ip'], FILTER_VALIDATE_IP)) {
        $mensaje = 'La dirección IP no tiene un formato válido.';
    } elseif ($dispositivo['mac'] !== '' && !preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $dispositivo['mac'])) {
        $mensaje = 'La MAC debe tener un formato como AA:BB:CC:DD:EE:FF.';
    } elseif (strlen($dispositivo['observaciones']) > 60000) {
        $mensaje = 'Las observaciones son demasiado largas.';
    }

    if ($mensaje === '') {
        try {
            $pdo->beginTransaction();
            $dispositivoActual = $dispositivo;

            if ($esEdicion) {
                $consultaActual = $pdo->prepare('SELECT * FROM dispositivos WHERE id_dispositivo = ? FOR UPDATE');
                $consultaActual->execute([$idDispositivo]);
                $dispositivoActual = $consultaActual->fetch();
                $dispositivo['activo'] = $dispositivoActual['activo'];
            }

            $consultaUbicacion = $pdo->prepare('SELECT activo FROM ubicaciones WHERE id_ubicacion = ? FOR UPDATE');
            $consultaUbicacion->execute([$dispositivo['id_ubicacion']]);
            $ubicacionElegida = $consultaUbicacion->fetch();
            $conservaUbicacion = $esEdicion && !$dispositivo['activo']
                && (int) $dispositivoActual['id_ubicacion'] === $dispositivo['id_ubicacion'];

            if (!$ubicacionElegida || (!$ubicacionElegida['activo'] && !$conservaUbicacion)) {
                $mensaje = 'Seleccioná una ubicación activa.';
            }

            if ($dispositivo['id_responsable']) {
                $consultaResponsable = $pdo->prepare('SELECT activo FROM usuarios WHERE id_usuario = ? FOR UPDATE');
                $consultaResponsable->execute([$dispositivo['id_responsable']]);
                $responsableElegido = $consultaResponsable->fetch();
                $conservaResponsable = $esEdicion && !$dispositivo['activo']
                    && (int) $dispositivoActual['id_responsable'] === $dispositivo['id_responsable'];

                if (!$responsableElegido || (!$responsableElegido['activo'] && !$conservaResponsable)) {
                    $mensaje = 'Seleccioná un responsable activo o dejalo sin asignar.';
                }
            }

            if ($mensaje === '') {
                $parametros = [
                    $dispositivo['codigo'], $dispositivo['tipo'], $dispositivo['marca'] ?: null,
                    $dispositivo['modelo'] ?: null, $dispositivo['numero_serie'] ?: null,
                    $dispositivo['sistema_operativo'] ?: null, $dispositivo['ip'] ?: null,
                    $dispositivo['mac'] ?: null, $dispositivo['id_ubicacion'],
                    $dispositivo['id_responsable'], $dispositivo['estado'], $dispositivo['observaciones'] ?: null
                ];

                if ($esEdicion) {
                    $sqlGuardar = "
                        UPDATE dispositivos
                        SET codigo = ?, tipo = ?, marca = ?, modelo = ?, numero_serie = ?,
                            sistema_operativo = ?, ip = ?, mac = ?, id_ubicacion = ?,
                            id_responsable = ?, estado = ?, observaciones = ?
                        WHERE id_dispositivo = ?";
                    $parametros[] = $idDispositivo;
                } else {
                    $sqlGuardar = "
                        INSERT INTO dispositivos
                            (codigo, tipo, marca, modelo, numero_serie, sistema_operativo,
                             ip, mac, id_ubicacion, id_responsable, estado, observaciones)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                }

                $consultaGuardar = $pdo->prepare($sqlGuardar);
                $consultaGuardar->execute($parametros);
                $idGuardado = $esEdicion ? $idDispositivo : (int) $pdo->lastInsertId();
                $pdo->commit();
                $_SESSION['mensaje_exito'] = 'Dispositivo guardado correctamente.';
                header('Location: detalle.php?id=' . $idGuardado);
                exit;
            }

            $pdo->rollBack();
        } catch (PDOException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensaje = errorGuardado($error, 'Ese código ya existe. Revisá también los dispositivos dados de baja.');
        }
    }
}

// Incluir una relación actual inactiva para no ocultar la información histórica.
$sqlUbicaciones = "
    SELECT id_ubicacion, nombre, activo
    FROM ubicaciones
    WHERE activo = 1 OR id_ubicacion = ?
    ORDER BY nombre";
$consultaUbicaciones = $pdo->prepare($sqlUbicaciones);
$consultaUbicaciones->execute([$dispositivo['id_ubicacion'] ?: null]);
$ubicaciones = $consultaUbicaciones->fetchAll();
$sqlResponsables = "
    SELECT id_usuario, nombre, activo
    FROM usuarios
    WHERE activo = 1 OR id_usuario = ?
    ORDER BY nombre";
$consultaResponsables = $pdo->prepare($sqlResponsables);
$consultaResponsables->execute([$dispositivo['id_responsable'] ?: null]);
$responsables = $consultaResponsables->fetchAll();
$token = tokenFormulario();

require_once __DIR__ . '/../includes/encabezado.php';
?>
<h1><?= escapar($titulo) ?></h1>
<p class="texto-secundario">Código, tipo, ubicación y estado son obligatorios. Los demás datos son opcionales.</p>
<?php if ($mensaje !== ''): ?>
    <p class="alerta" role="alert"><?= escapar($mensaje) ?></p>
<?php endif; ?>
<form method="post" class="formulario formulario-edicion">
    <input type="hidden" name="token" value="<?= escapar($token) ?>">
    <?php foreach ($camposTexto as $campo => $configuracion): ?>
        <label for="<?= escapar($campo) ?>"><?= escapar($configuracion['etiqueta']) ?></label>
        <input id="<?= escapar($campo) ?>" name="<?= escapar($campo) ?>" maxlength="<?= (int) $configuracion['limite'] ?>"
               value="<?= escapar($dispositivo[$campo]) ?>" <?= $campo === 'codigo' ? 'required' : '' ?>>
    <?php endforeach; ?>
    <label for="tipo">Tipo</label>
    <select id="tipo" name="tipo" required>
        <?php foreach ($tiposDispositivo as $valorTipo => $nombreTipo): ?>
            <option value="<?= escapar($valorTipo) ?>" <?= $dispositivo['tipo'] === $valorTipo ? 'selected' : '' ?>><?= escapar($nombreTipo) ?></option>
        <?php endforeach; ?>
    </select>
    <label for="id_ubicacion">Ubicación</label>
    <select id="id_ubicacion" name="id_ubicacion" required>
        <option value="">Seleccionar ubicación</option>
        <?php foreach ($ubicaciones as $ubicacion): ?>
            <option value="<?= (int) $ubicacion['id_ubicacion'] ?>" <?= (int) $dispositivo['id_ubicacion'] === (int) $ubicacion['id_ubicacion'] ? 'selected' : '' ?>><?= escapar($ubicacion['nombre']) ?><?= !$ubicacion['activo'] ? ' (inactiva)' : '' ?></option>
        <?php endforeach; ?>
    </select>
    <label for="id_responsable">Responsable</label>
    <select id="id_responsable" name="id_responsable">
        <option value="">Sin asignar</option>
        <?php foreach ($responsables as $responsable): ?>
            <option value="<?= (int) $responsable['id_usuario'] ?>" <?= (int) $dispositivo['id_responsable'] === (int) $responsable['id_usuario'] ? 'selected' : '' ?>><?= escapar($responsable['nombre']) ?><?= !$responsable['activo'] ? ' (inactivo)' : '' ?></option>
        <?php endforeach; ?>
    </select>
    <label for="estado">Estado técnico</label>
    <select id="estado" name="estado" required>
        <?php foreach ($estadosDispositivo as $valorEstado => $nombreEstado): ?>
            <option value="<?= escapar($valorEstado) ?>" <?= $dispositivo['estado'] === $valorEstado ? 'selected' : '' ?>><?= escapar($nombreEstado) ?></option>
        <?php endforeach; ?>
    </select>
    <label for="observaciones">Observaciones</label>
    <textarea id="observaciones" name="observaciones" rows="4" maxlength="15000"><?= escapar($dispositivo['observaciones']) ?></textarea>
    <div class="acciones">
        <button class="boton" type="submit">Guardar dispositivo</button>
        <a class="boton boton-secundario" href="index.php">Cancelar</a>
    </div>
</form>
<?php require_once __DIR__ . '/../includes/pie.php'; ?>
