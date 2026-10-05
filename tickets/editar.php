<?php
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/opciones.php';

$usuario = exigirSesion('../login.php');
exigirRoles($usuario, ['administrador', 'tecnico']);
$pdo = getConexion();
$rutaBase = '../';
$idTicket = idEntrada($_GET, 'id');
$titulo = 'Gestionar ticket';
$mensaje = '';
$novedad = '';
$consultaTicket = $pdo->prepare('SELECT * FROM tickets WHERE id_ticket = ?');
$consultaTicket->execute([$idTicket]);
$ticket = $consultaTicket->fetch();

if (!$ticket) {
    http_response_code(404);
    exit('El ticket no existe.');
}
if (in_array($ticket['estado'], ['resuelto', 'cancelado'], true)) {
    http_response_code(409);
    exit('El ticket ya está finalizado. Podés consultar su detalle desde el listado.');
}

$datosFormulario = $ticket;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datosFormulario['id_tecnico'] = idEntrada($_POST, 'id_tecnico');
    $datosFormulario['prioridad'] = textoEntrada($_POST, 'prioridad');
    $datosFormulario['estado'] = textoEntrada($_POST, 'estado');
    $datosFormulario['diagnostico'] = textoEntrada($_POST, 'diagnostico');
    $datosFormulario['solucion'] = textoEntrada($_POST, 'solucion');
    $datosFormulario['motivo_cancelacion'] = textoEntrada($_POST, 'motivo_cancelacion');
    $novedad = textoEntrada($_POST, 'novedad');

    if (!tokenValido()) {
        $mensaje = 'El formulario venció. Intentá nuevamente.';
    } elseif (!isset($prioridadesTicket[$datosFormulario['prioridad']], $estadosTicket[$datosFormulario['estado']])) {
        $mensaje = 'Elegí una prioridad y un estado válidos.';
    } elseif (textoEntrada($_POST, 'id_tecnico') !== '' && !$datosFormulario['id_tecnico']) {
        $mensaje = 'El técnico seleccionado no es válido.';
    }

    foreach (['diagnostico', 'solucion', 'motivo_cancelacion'] as $campo) {
        if (mb_strlen($datosFormulario[$campo]) > 10000) {
            $mensaje = 'Los textos no deben superar los 10000 caracteres.';
        }
    }
    if (mb_strlen($novedad) > 10000) {
        $mensaje = 'La novedad no debe superar los 10000 caracteres.';
    }

    if ($mensaje === '') {
        try {
            $pdo->beginTransaction();
            $consultaActual = $pdo->prepare('SELECT * FROM tickets WHERE id_ticket = ? FOR UPDATE');
            $consultaActual->execute([$idTicket]);
            $ticket = $consultaActual->fetch();
            $estadoNuevo = $datosFormulario['estado'];
            $nombreTecnico = 'Sin asignar';

            if (!in_array($estadoNuevo, $transicionesTicket[$ticket['estado']], true)) {
                $mensaje = 'Ese cambio de estado no está permitido. Revisá el estado actual del ticket.';
            } elseif (in_array($estadoNuevo, ['en_proceso', 'en_espera_repuesto', 'resuelto'], true) && !$datosFormulario['id_tecnico']) {
                $mensaje = 'Asigná un Técnico o Administrador para atender el ticket.';
            } elseif ($estadoNuevo === 'resuelto' && ($datosFormulario['diagnostico'] === '' || $datosFormulario['solucion'] === '')) {
                $mensaje = 'Para resolver, completá el diagnóstico y la solución.';
            } elseif ($estadoNuevo === 'cancelado' && $datosFormulario['motivo_cancelacion'] === '') {
                $mensaje = 'Para cancelar, indicá el motivo.';
            } elseif ($estadoNuevo === 'en_espera_repuesto' && $ticket['estado'] !== $estadoNuevo && $novedad === '') {
                $mensaje = 'Explicá en la novedad qué repuesto falta.';
            }

            if ($datosFormulario['id_tecnico']) {
                $consultaTecnico = $pdo->prepare('SELECT nombre, rol, activo FROM usuarios WHERE id_usuario = ? FOR UPDATE');
                $consultaTecnico->execute([$datosFormulario['id_tecnico']]);
                $tecnico = $consultaTecnico->fetch();

                if (!$tecnico || !$tecnico['activo'] || !in_array($tecnico['rol'], ['administrador', 'tecnico'], true)) {
                    $mensaje = 'Seleccioná un Técnico o Administrador activo.';
                } else {
                    $nombreTecnico = $tecnico['nombre'];
                }
            }

            // Registrar solo cambios reales, además de la novedad que escriba el técnico.
            $cambios = [];
            if ($ticket['estado'] !== $estadoNuevo) {
                $cambios[] = 'Estado: ' . $estadosTicket[$ticket['estado']] . ' → ' . $estadosTicket[$estadoNuevo] . '.';
            }
            if ($ticket['prioridad'] !== $datosFormulario['prioridad']) {
                $cambios[] = 'Prioridad: ' . $prioridadesTicket[$ticket['prioridad']] . ' → ' . $prioridadesTicket[$datosFormulario['prioridad']] . '.';
            }
            if ((int) $ticket['id_tecnico'] !== (int) $datosFormulario['id_tecnico']) {
                $cambios[] = 'Responsable asignado: ' . $nombreTecnico . '.';
            }
            foreach (['diagnostico' => 'Diagnóstico', 'solucion' => 'Solución'] as $campo => $etiqueta) {
                if (($ticket[$campo] ?? '') !== $datosFormulario[$campo]) {
                    $cambios[] = $etiqueta . ': ' . ($datosFormulario[$campo] !== '' ? $datosFormulario[$campo] : 'Sin registrar') . '.';
                }
            }
            if ($estadoNuevo === 'cancelado') {
                $cambios[] = 'Motivo de cancelación: ' . $datosFormulario['motivo_cancelacion'];
            }
            if ($novedad !== '') {
                $cambios[] = 'Novedad: ' . $novedad;
            }
            if (!$cambios && $mensaje === '') {
                $mensaje = 'No hay cambios para guardar. Podés escribir una novedad.';
            }
            if (strlen(implode("\n", $cambios)) > 60000) {
                $mensaje = 'El seguimiento es demasiado extenso. Resumí los textos antes de guardar.';
            }

            if ($mensaje === '') {
                $finalizado = in_array($estadoNuevo, ['resuelto', 'cancelado'], true);
                $fechaCierre = $finalizado ? date('Y-m-d H:i:s') : null;
                $motivoCancelacion = $estadoNuevo === 'cancelado' ? $datosFormulario['motivo_cancelacion'] : null;
                $sqlGuardar = "
                    UPDATE tickets
                    SET id_tecnico = ?, prioridad = ?, estado = ?, diagnostico = ?,
                        solucion = ?, motivo_cancelacion = ?, fecha_cierre = ?
                    WHERE id_ticket = ?";
                $consultaGuardar = $pdo->prepare($sqlGuardar);
                $consultaGuardar->execute([
                    $datosFormulario['id_tecnico'], $datosFormulario['prioridad'], $estadoNuevo,
                    $datosFormulario['diagnostico'] !== '' ? $datosFormulario['diagnostico'] : null,
                    $datosFormulario['solucion'] !== '' ? $datosFormulario['solucion'] : null,
                    $motivoCancelacion, $fechaCierre, $idTicket
                ]);

                $consultaSeguimiento = $pdo->prepare('INSERT INTO seguimiento_tickets (id_ticket, id_usuario, descripcion) VALUES (?, ?, ?)');
                $consultaSeguimiento->execute([$idTicket, $usuario['id_usuario'], implode("\n", $cambios)]);
                $pdo->commit();
                $_SESSION['mensaje_exito'] = 'Ticket actualizado y novedad registrada.';
                header('Location: detalle.php?id=' . $idTicket);
                exit;
            }
            $pdo->rollBack();
        } catch (PDOException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensaje = 'No se pudo guardar el cambio. Volvé a intentar.';
        }
    }
}

$sqlTecnicos = "SELECT id_usuario, nombre FROM usuarios
               WHERE activo = 1 AND rol IN ('administrador', 'tecnico')
               ORDER BY nombre";
$tecnicos = $pdo->query($sqlTecnicos)->fetchAll();
$estadosPermitidos = $transicionesTicket[$ticket['estado']];
$token = tokenFormulario();
require_once __DIR__ . '/../includes/encabezado.php';
?>
<h1>Gestionar #<?= $idTicket ?> · <?= escapar($ticket['titulo']) ?></h1>
<p class="texto-secundario">Los cambios quedan en el seguimiento. El reporte original se conserva.</p>
<?php if ($mensaje !== ''): ?>
    <p class="alerta" role="alert"><?= escapar($mensaje) ?></p>
<?php endif; ?>
<form method="post" class="formulario formulario-edicion">
    <input type="hidden" name="token" value="<?= escapar($token) ?>">
    <label for="id_tecnico">Responsable del seguimiento</label>
    <select id="id_tecnico" name="id_tecnico">
        <option value="">Sin asignar</option>
        <?php foreach ($tecnicos as $tecnico): ?>
            <option value="<?= (int) $tecnico['id_usuario'] ?>" <?= (int) $datosFormulario['id_tecnico'] === (int) $tecnico['id_usuario'] ? 'selected' : '' ?>><?= escapar($tecnico['nombre']) ?></option>
        <?php endforeach; ?>
    </select>
    <label for="prioridad">Prioridad</label>
    <select id="prioridad" name="prioridad" required>
        <?php foreach ($prioridadesTicket as $valorPrioridad => $nombrePrioridad): ?>
            <option value="<?= escapar($valorPrioridad) ?>" <?= $datosFormulario['prioridad'] === $valorPrioridad ? 'selected' : '' ?>><?= escapar($nombrePrioridad) ?></option>
        <?php endforeach; ?>
    </select>
    <label for="estado">Estado</label>
    <select id="estado" name="estado" required>
        <?php foreach ($estadosPermitidos as $estadoPermitido): ?>
            <option value="<?= escapar($estadoPermitido) ?>" <?= $datosFormulario['estado'] === $estadoPermitido ? 'selected' : '' ?>><?= escapar($estadosTicket[$estadoPermitido]) ?></option>
        <?php endforeach; ?>
    </select>
    <label for="diagnostico">Diagnóstico (obligatorio al resolver)</label>
    <textarea id="diagnostico" name="diagnostico" rows="3" maxlength="10000"><?= escapar($datosFormulario['diagnostico']) ?></textarea>
    <label for="solucion">Solución (obligatoria al resolver)</label>
    <textarea id="solucion" name="solucion" rows="3" maxlength="10000"><?= escapar($datosFormulario['solucion']) ?></textarea>
    <label for="motivo_cancelacion">Motivo (solo para cancelar)</label>
    <textarea id="motivo_cancelacion" name="motivo_cancelacion" rows="2" maxlength="10000"><?= escapar($datosFormulario['motivo_cancelacion']) ?></textarea>
    <label for="novedad">Novedad / observaciones</label>
    <textarea id="novedad" name="novedad" rows="3" maxlength="10000"><?= escapar($novedad) ?></textarea>
    <p class="ayuda">Podés anotar, por ejemplo: «Se llevó a reparar a un taller». El reportante podrá leer este seguimiento. Los datos sensibles de seguridad corresponden al registro de incidentes.</p>
    <div class="acciones">
        <button class="boton" type="submit">Guardar seguimiento</button>
        <a class="boton boton-secundario" href="detalle.php?id=<?= $idTicket ?>">Volver al ticket</a>
    </div>
</form>
<?php require_once __DIR__ . '/../includes/pie.php'; ?>
