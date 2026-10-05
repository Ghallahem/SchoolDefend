<?php
require_once __DIR__ . '/../includes/sesion.php';

$usuario = exigirSesion('../login.php');
exigirRoles($usuario, ['administrador']);
$pdo = getConexion();
$rutaBase = '../';
$idUbicacion = idEntrada($_GET, 'id');
$consultaUbicacion = $pdo->prepare('SELECT * FROM ubicaciones WHERE id_ubicacion = ?');
$consultaUbicacion->execute([$idUbicacion]);
$registro = $consultaUbicacion->fetch();

if (!$registro) {
    http_response_code(404);
    exit('La ubicación no existe.');
}

$mensaje = '';
$motivo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motivo = textoEntrada($_POST, 'motivo');
    $accion = textoEntrada($_POST, 'accion');

    if (!tokenValido()) {
        $mensaje = 'El formulario venció. Intentá nuevamente.';
    } elseif (!in_array($accion, ['baja', 'reactivar'], true)) {
        $mensaje = 'Acción no válida.';
    } elseif ($accion === 'baja' && ($motivo === '' || mb_strlen($motivo) > 255)) {
        $mensaje = 'Indicá el motivo de baja, de hasta 255 caracteres.';
    } else {
        try {
            $pdo->beginTransaction();
            $consultaActual = $pdo->prepare('SELECT * FROM ubicaciones WHERE id_ubicacion = ? FOR UPDATE');
            $consultaActual->execute([$idUbicacion]);
            $registro = $consultaActual->fetch();

            if (($accion === 'baja') !== (bool) $registro['activo']) {
                $mensaje = 'El estado cambió. Volvé al listado para revisarlo.';
            } elseif ($accion === 'baja') {
                $sqlPendientes = "
                    SELECT
                        (SELECT COUNT(*) FROM dispositivos WHERE id_ubicacion = ? AND activo = 1) AS equipos,
                        (SELECT COUNT(*) FROM tickets WHERE id_ubicacion = ? AND estado NOT IN ('resuelto', 'cancelado')) AS tickets";
                $consultaPendientes = $pdo->prepare($sqlPendientes);
                $consultaPendientes->execute([$idUbicacion, $idUbicacion]);
                $pendientes = $consultaPendientes->fetch();

                if ($pendientes['equipos'] || $pendientes['tickets']) {
                    $mensaje = 'Reubicá los equipos activos y atendé los tickets pendientes antes de dar de baja este sector.';
                }
            }

            if ($mensaje === '') {
                if ($accion === 'baja') {
                    $sqlCambio = "UPDATE ubicaciones
                                  SET activo = 0, fecha_baja = NOW(), id_usuario_baja = ?, motivo_baja = ?
                                  WHERE id_ubicacion = ?";
                    $parametros = [$usuario['id_usuario'], $motivo, $idUbicacion];
                } else {
                    $sqlCambio = 'UPDATE ubicaciones SET activo = 1 WHERE id_ubicacion = ?';
                    $parametros = [$idUbicacion];
                }

                $consultaCambio = $pdo->prepare($sqlCambio);
                $consultaCambio->execute($parametros);
                $pdo->commit();
                $_SESSION['mensaje_exito'] = 'Estado de la ubicación actualizado.';
                header('Location: index.php?activo=todos');
                exit;
            }

            $pdo->rollBack();
        } catch (PDOException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensaje = errorGuardado($error, 'No se pudo actualizar la ubicación.');
        }
    }
}

$titulo = $registro['activo'] ? 'Dar de baja ubicación' : 'Reactivar ubicación';
$nombreRegistro = $registro['nombre'];
$accionFormulario = $registro['activo'] ? 'baja' : 'reactivar';
$token = tokenFormulario();
require_once __DIR__ . '/../includes/encabezado.php';
require_once __DIR__ . '/../includes/confirmar_baja.php';
require_once __DIR__ . '/../includes/pie.php';
