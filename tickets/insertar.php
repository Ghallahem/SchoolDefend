<?php
require_once __DIR__ . '/../includes/sesion.php';

$usuario = exigirSesion('../login.php');
$pdo = getConexion();
$rutaBase = '../';
$titulo = 'Nuevo ticket';
$mensaje = '';
$tituloTicket = '';
$descripcion = '';
$idUbicacion = null;
$idDispositivo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tituloTicket = textoEntrada($_POST, 'titulo');
    $descripcion = textoEntrada($_POST, 'descripcion');
    $idUbicacion = idEntrada($_POST, 'id_ubicacion');
    $idDispositivo = idEntrada($_POST, 'id_dispositivo');

    if (!tokenValido()) {
        $mensaje = 'El formulario venció. Intentá nuevamente.';
    } elseif ($tituloTicket === '' || mb_strlen($tituloTicket) > 150 || $descripcion === '' || mb_strlen($descripcion) > 10000) {
        $mensaje = 'Ingresá un título de hasta 150 caracteres y una descripción de hasta 10000.';
    } elseif (!$idUbicacion || (textoEntrada($_POST, 'id_dispositivo') !== '' && !$idDispositivo)) {
        $mensaje = 'Seleccioná una ubicación válida y, si corresponde, un dispositivo.';
    } else {
        try {
            $pdo->beginTransaction();

            if ($idDispositivo) {
                $consultaEquipo = $pdo->prepare('SELECT activo, id_ubicacion FROM dispositivos WHERE id_dispositivo = ? FOR UPDATE');
                $consultaEquipo->execute([$idDispositivo]);
                $equipo = $consultaEquipo->fetch();

                if (!$equipo || !$equipo['activo'] || (int) $equipo['id_ubicacion'] !== $idUbicacion) {
                    $mensaje = 'El equipo debe estar activo y pertenecer a la ubicación seleccionada.';
                }
            }

            $consultaUbicacion = $pdo->prepare('SELECT activo FROM ubicaciones WHERE id_ubicacion = ? FOR UPDATE');
            $consultaUbicacion->execute([$idUbicacion]);

            if (!$consultaUbicacion->fetchColumn()) {
                $mensaje = 'Seleccioná una ubicación activa.';
            }

            if ($mensaje === '') {
                // Creador, prioridad y estado se fijan aquí, sin tomarlos del formulario.
                $sqlTicket = "
                    INSERT INTO tickets (titulo, descripcion, id_ubicacion, id_dispositivo, id_creador, prioridad, estado)
                    VALUES (?, ?, ?, ?, ?, 'media', 'abierto')";
                $consultaTicket = $pdo->prepare($sqlTicket);
                $consultaTicket->execute([$tituloTicket, $descripcion, $idUbicacion, $idDispositivo, $usuario['id_usuario']]);
                $idTicket = (int) $pdo->lastInsertId();

                $sqlSeguimiento = 'INSERT INTO seguimiento_tickets (id_ticket, id_usuario, descripcion) VALUES (?, ?, ?)';
                $consultaSeguimiento = $pdo->prepare($sqlSeguimiento);
                $consultaSeguimiento->execute([$idTicket, $usuario['id_usuario'], 'Ticket creado. Estado: Abierto.']);
                $pdo->commit();

                $_SESSION['mensaje_exito'] = 'Ticket registrado correctamente.';
                header('Location: detalle.php?id=' . $idTicket);
                exit;
            }

            $pdo->rollBack();
        } catch (PDOException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensaje = 'No se pudo registrar el ticket. Intentá nuevamente.';
        }
    }
}

$ubicaciones = $pdo->query('SELECT id_ubicacion, nombre FROM ubicaciones WHERE activo = 1 ORDER BY nombre')->fetchAll();
$sqlDispositivos = "
    SELECT dispositivos.id_dispositivo, dispositivos.codigo, ubicaciones.nombre AS ubicacion
    FROM dispositivos
    INNER JOIN ubicaciones ON dispositivos.id_ubicacion = ubicaciones.id_ubicacion
    WHERE dispositivos.activo = 1 AND ubicaciones.activo = 1
    ORDER BY ubicaciones.nombre, dispositivos.codigo";
$dispositivos = $pdo->query($sqlDispositivos)->fetchAll();
$token = tokenFormulario();

require_once __DIR__ . '/../includes/encabezado.php';
?>
<h1>Nuevo ticket</h1>
<p class="texto-secundario">Describí el problema y su ubicación. Podés dejar el equipo sin seleccionar si es una falla general.</p>
<?php if ($mensaje !== ''): ?>
    <p class="alerta" role="alert"><?= escapar($mensaje) ?></p>
<?php endif; ?>
<form method="post" class="formulario formulario-edicion">
    <input type="hidden" name="token" value="<?= escapar($token) ?>">
    <label for="titulo">Título</label>
    <input id="titulo" name="titulo" maxlength="150" value="<?= escapar($tituloTicket) ?>" required>
    <label for="descripcion">Descripción del problema</label>
    <textarea id="descripcion" name="descripcion" rows="5" maxlength="10000" required><?= escapar($descripcion) ?></textarea>
    <label for="id_ubicacion">Ubicación</label>
    <select id="id_ubicacion" name="id_ubicacion" required>
        <option value="">Seleccionar ubicación</option>
        <?php foreach ($ubicaciones as $ubicacion): ?>
            <option value="<?= (int) $ubicacion['id_ubicacion'] ?>" <?= $idUbicacion === (int) $ubicacion['id_ubicacion'] ? 'selected' : '' ?>><?= escapar($ubicacion['nombre']) ?></option>
        <?php endforeach; ?>
    </select>
    <label for="id_dispositivo">Equipo (opcional, de la misma ubicación)</label>
    <select id="id_dispositivo" name="id_dispositivo">
        <option value="">Problema general / equipo sin identificar</option>
        <?php foreach ($dispositivos as $dispositivo): ?>
            <option value="<?= (int) $dispositivo['id_dispositivo'] ?>" <?= $idDispositivo === (int) $dispositivo['id_dispositivo'] ? 'selected' : '' ?>><?= escapar($dispositivo['ubicacion']) ?> · <?= escapar($dispositivo['codigo']) ?></option>
        <?php endforeach; ?>
    </select>
    <div class="acciones">
        <button class="boton" type="submit">Registrar ticket</button>
        <a class="boton boton-secundario" href="index.php">Cancelar</a>
    </div>
</form>
<?php require_once __DIR__ . '/../includes/pie.php'; ?>
