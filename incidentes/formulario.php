<?php
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/opciones.php';

$usuario = exigirSesion('../login.php');
exigirRoles($usuario, ['administrador', 'tecnico']);
$pdo = getConexion();
$rutaBase = '../';
$idIncidente = idEntrada($_GET, 'id');
$esEdicion = isset($_GET['id']);
$titulo = $esEdicion ? 'Gestionar incidente' : 'Registrar incidente';
$mensaje = '';
$incidente = [
    'titulo' => '', 'tipo' => 'otro', 'descripcion' => '', 'severidad' => 'media',
    'estado' => 'reportado', 'id_responsable' => $usuario['id_usuario'],
    'id_dispositivo' => null, 'id_ticket' => null, 'acciones_realizadas' => '', 'conclusion' => ''
];
if ($esEdicion) {
    $consultaIncidente = $pdo->prepare('SELECT * FROM incidentes WHERE id_incidente = ?');
    $consultaIncidente->execute([$idIncidente]);
    $incidente = $consultaIncidente->fetch();
    if (!$incidente) {
        http_response_code(404);
        exit('El incidente no existe.');
    }
    if ($incidente['estado'] === 'cerrado') {
        http_response_code(409);
        exit('El incidente ya está cerrado. Podés consultar su detalle.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // El reporte y sus relaciones se conservan al gestionar un incidente existente.
    if (!$esEdicion) {
        $incidente['titulo'] = textoEntrada($_POST, 'titulo');
        $incidente['tipo'] = textoEntrada($_POST, 'tipo');
        $incidente['descripcion'] = textoEntrada($_POST, 'descripcion');
        $incidente['id_dispositivo'] = idEntrada($_POST, 'id_dispositivo');
        $incidente['id_ticket'] = idEntrada($_POST, 'id_ticket');
    }
    $incidente['severidad'] = textoEntrada($_POST, 'severidad');
    $incidente['id_responsable'] = idEntrada($_POST, 'id_responsable');
    $incidente['acciones_realizadas'] = textoEntrada($_POST, 'acciones_realizadas');
    $incidente['conclusion'] = textoEntrada($_POST, 'conclusion');
    $incidente['estado'] = $esEdicion ? textoEntrada($_POST, 'estado') : 'reportado';

    if (!tokenValido()) {
        $mensaje = 'El formulario venció. Intentá nuevamente.';
    } elseif ($incidente['titulo'] === '' || mb_strlen($incidente['titulo']) > 150 || $incidente['descripcion'] === '') {
        $mensaje = 'Completá el título (hasta 150 caracteres) y la descripción.';
    } elseif (!isset($tiposIncidente[$incidente['tipo']], $severidadesIncidente[$incidente['severidad']], $estadosIncidente[$incidente['estado']])) {
        $mensaje = 'Seleccioná tipo, severidad y estado válidos.';
    } elseif (!$incidente['id_responsable']) {
        $mensaje = 'Seleccioná un responsable.';
    } elseif ($incidente['estado'] === 'cerrado' && ($incidente['acciones_realizadas'] === '' || $incidente['conclusion'] === '')) {
        $mensaje = 'Para cerrar, completá las acciones realizadas y la conclusión.';
    }
    foreach (['descripcion', 'acciones_realizadas', 'conclusion'] as $campoTexto) {
        if (mb_strlen($incidente[$campoTexto]) > 10000) {
            $mensaje = 'Los textos no deben superar los 10000 caracteres.';
        }
    }
    if (!$esEdicion) {
        foreach (['id_dispositivo', 'id_ticket'] as $campoRelacion) {
            if (textoEntrada($_POST, $campoRelacion) !== '' && !$incidente[$campoRelacion]) {
                $mensaje = 'Seleccioná un equipo y un ticket válidos o dejalos vacíos.';
            }
        }
    }

    if ($mensaje === '') {
        try {
            $pdo->beginTransaction();
            if ($esEdicion) {
                $consultaActual = $pdo->prepare('SELECT estado FROM incidentes WHERE id_incidente = ? FOR UPDATE');
                $consultaActual->execute([$idIncidente]);
                $estadoActual = $consultaActual->fetchColumn();
                if ($estadoActual === 'cerrado') {
                    $mensaje = 'El incidente ya fue cerrado por otro usuario.';
                } elseif ($estadoActual === 'en_investigacion' && $incidente['estado'] === 'reportado') {
                    $mensaje = 'Un incidente en investigación no vuelve a reportado.';
                }
            }
            $sqlResponsable = "
                SELECT id_usuario FROM usuarios
                WHERE id_usuario = ? AND activo = 1 AND rol IN ('administrador', 'tecnico')
                FOR UPDATE";
            $consultaResponsable = $pdo->prepare($sqlResponsable);
            $consultaResponsable->execute([$incidente['id_responsable']]);
            if (!$consultaResponsable->fetch()) {
                $mensaje = 'Seleccioná un Técnico o Administrador activo.';
            }
            if (!$esEdicion && $incidente['id_dispositivo']) {
                $consultaEquipo = $pdo->prepare('SELECT activo FROM dispositivos WHERE id_dispositivo = ? FOR UPDATE');
                $consultaEquipo->execute([$incidente['id_dispositivo']]);
                if (!$consultaEquipo->fetchColumn()) {
                    $mensaje = 'Seleccioná un equipo activo.';
                }
            }
            if (!$esEdicion && $incidente['id_ticket']) {
                $consultaTicket = $pdo->prepare('SELECT id_dispositivo FROM tickets WHERE id_ticket = ?');
                $consultaTicket->execute([$incidente['id_ticket']]);
                $ticketRelacionado = $consultaTicket->fetch();
                if (!$ticketRelacionado) {
                    $mensaje = 'El ticket seleccionado no existe.';
                } elseif ($incidente['id_dispositivo'] && $ticketRelacionado['id_dispositivo'] && $incidente['id_dispositivo'] !== (int) $ticketRelacionado['id_dispositivo']) {
                    $mensaje = 'El equipo no coincide con el del ticket seleccionado.';
                }
            }
            if ($mensaje === '') {
                $fechaCierre = $incidente['estado'] === 'cerrado' ? date('Y-m-d H:i:s') : null;
                if ($esEdicion) {
                    $sqlGuardar = "
                        UPDATE incidentes
                        SET severidad = ?, estado = ?, id_responsable = ?,
                            acciones_realizadas = ?, conclusion = ?, fecha_cierre = ?
                        WHERE id_incidente = ?";
                    $parametrosGuardar = [
                        $incidente['severidad'], $incidente['estado'], $incidente['id_responsable'],
                        $incidente['acciones_realizadas'] ?: null, $incidente['conclusion'] ?: null,
                        $fechaCierre, $idIncidente
                    ];
                } else {
                    $sqlGuardar = "
                        INSERT INTO incidentes
                            (titulo, tipo, descripcion, severidad, id_responsable, id_dispositivo,
                             id_ticket, id_creador, acciones_realizadas, conclusion)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $parametrosGuardar = [
                        $incidente['titulo'], $incidente['tipo'], $incidente['descripcion'],
                        $incidente['severidad'], $incidente['id_responsable'], $incidente['id_dispositivo'],
                        $incidente['id_ticket'], $usuario['id_usuario'],
                        $incidente['acciones_realizadas'] ?: null, $incidente['conclusion'] ?: null
                    ];
                }
                $consultaGuardar = $pdo->prepare($sqlGuardar);
                $consultaGuardar->execute($parametrosGuardar);
                if (!$esEdicion) {
                    $idIncidente = (int) $pdo->lastInsertId();
                }
                $pdo->commit();
                $_SESSION['mensaje_exito'] = 'Incidente guardado correctamente.';
                header('Location: detalle.php?id=' . $idIncidente);
                exit;
            }
            $pdo->rollBack();
        } catch (PDOException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensaje = 'No se pudo guardar. Volvé a intentar.';
        }
    }
}

$sqlResponsables = "
    SELECT id_usuario, nombre FROM usuarios
    WHERE activo = 1 AND rol IN ('administrador', 'tecnico')
    ORDER BY nombre";
$responsables = $pdo->query($sqlResponsables)->fetchAll();
$equipos = [];
$tickets = [];
if (!$esEdicion) {
    $equipos = $pdo->query('SELECT id_dispositivo, codigo FROM dispositivos WHERE activo = 1 ORDER BY codigo')->fetchAll();
    $tickets = $pdo->query('SELECT id_ticket, titulo FROM tickets ORDER BY id_ticket DESC')->fetchAll();
}
$token = tokenFormulario();
require_once __DIR__ . '/../includes/encabezado.php';
?>
<h1><?= escapar($titulo) ?></h1>
<?php if ($mensaje !== ''): ?>
    <p class="alerta" role="alert"><?= escapar($mensaje) ?></p>
<?php endif; ?>
<form method="post" class="formulario formulario-edicion">
    <input type="hidden" name="token" value="<?= escapar($token) ?>">
    <?php if (!$esEdicion): ?>
        <label for="titulo">Título</label>
        <input id="titulo" name="titulo" maxlength="150" value="<?= escapar($incidente['titulo']) ?>" required>
        <label for="tipo">Tipo</label>
        <select id="tipo" name="tipo" required>
            <?php foreach ($tiposIncidente as $valorTipo => $nombreTipo): ?>
                <option value="<?= escapar($valorTipo) ?>" <?= $incidente['tipo'] === $valorTipo ? 'selected' : '' ?>><?= escapar($nombreTipo) ?></option>
            <?php endforeach; ?>
        </select>
        <label for="descripcion">Descripción</label>
        <textarea id="descripcion" name="descripcion" rows="4" maxlength="10000" required><?= escapar($incidente['descripcion']) ?></textarea>
        <label for="id_dispositivo">Equipo (opcional)</label>
        <select id="id_dispositivo" name="id_dispositivo">
            <option value="">Sin identificar</option>
            <?php foreach ($equipos as $equipo): ?>
                <option value="<?= (int) $equipo['id_dispositivo'] ?>" <?= (int) $incidente['id_dispositivo'] === (int) $equipo['id_dispositivo'] ? 'selected' : '' ?>><?= escapar($equipo['codigo']) ?></option>
            <?php endforeach; ?>
        </select>
        <label for="id_ticket">Ticket relacionado (opcional)</label>
        <select id="id_ticket" name="id_ticket">
            <option value="">Sin ticket relacionado</option>
            <?php foreach ($tickets as $ticket): ?>
                <option value="<?= (int) $ticket['id_ticket'] ?>" <?= (int) $incidente['id_ticket'] === (int) $ticket['id_ticket'] ? 'selected' : '' ?>>#<?= (int) $ticket['id_ticket'] ?> · <?= escapar($ticket['titulo']) ?></option>
            <?php endforeach; ?>
        </select>
    <?php else: ?>
        <h2><?= escapar($incidente['titulo']) ?></h2>
        <p class="texto-secundario">El reporte original se conserva. Completá las acciones y la conclusión para cerrar.</p>
        <label for="estado">Estado</label>
        <select id="estado" name="estado" required>
            <?php foreach ($estadosIncidente as $valorEstado => $nombreEstado): ?>
                <option value="<?= escapar($valorEstado) ?>" <?= $incidente['estado'] === $valorEstado ? 'selected' : '' ?>><?= escapar($nombreEstado) ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <label for="severidad">Severidad</label>
    <select id="severidad" name="severidad" required>
        <?php foreach ($severidadesIncidente as $valorSeveridad => $nombreSeveridad): ?>
            <option value="<?= escapar($valorSeveridad) ?>" <?= $incidente['severidad'] === $valorSeveridad ? 'selected' : '' ?>><?= escapar($nombreSeveridad) ?></option>
        <?php endforeach; ?>
    </select>
    <label for="id_responsable">Responsable</label>
    <select id="id_responsable" name="id_responsable" required>
        <?php foreach ($responsables as $responsable): ?>
            <option value="<?= (int) $responsable['id_usuario'] ?>" <?= (int) $incidente['id_responsable'] === (int) $responsable['id_usuario'] ? 'selected' : '' ?>><?= escapar($responsable['nombre']) ?></option>
        <?php endforeach; ?>
    </select>
    <label for="acciones_realizadas">Acciones realizadas</label>
    <textarea id="acciones_realizadas" name="acciones_realizadas" rows="4" maxlength="10000"><?= escapar($incidente['acciones_realizadas']) ?></textarea>
    <label for="conclusion">Conclusión</label>
    <textarea id="conclusion" name="conclusion" rows="3" maxlength="10000"><?= escapar($incidente['conclusion']) ?></textarea>
    <div class="acciones">
        <button class="boton" type="submit">Guardar incidente</button>
        <a class="boton boton-secundario" href="index.php">Volver a incidentes</a>
    </div>
</form>
<?php require_once __DIR__ . '/../includes/pie.php'; ?>
