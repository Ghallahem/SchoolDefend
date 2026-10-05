<?php
require_once __DIR__ . '/../includes/sesion.php';

$usuario = exigirSesion('../login.php');
exigirRoles($usuario, ['administrador', 'tecnico']);
$pdo = getConexion();
$rutaBase = '../';
$idDispositivo = idEntrada($_GET, 'id');
$consultaDispositivo = $pdo->prepare('SELECT * FROM dispositivos WHERE id_dispositivo = ?');
$consultaDispositivo->execute([$idDispositivo]);
$registro = $consultaDispositivo->fetch();

if (!$registro) {
    http_response_code(404);
    exit('El dispositivo no existe.');
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
            $consultaActual = $pdo->prepare('SELECT * FROM dispositivos WHERE id_dispositivo = ? FOR UPDATE');
            $consultaActual->execute([$idDispositivo]);
            $registro = $consultaActual->fetch();

            if (($accion === 'baja') !== (bool) $registro['activo']) {
                $mensaje = 'El estado cambió. Volvé al listado para revisarlo.';
            } elseif ($accion === 'baja') {
                $sqlPendientes = "
                    SELECT
                        (SELECT COUNT(*) FROM tickets WHERE id_dispositivo = ? AND estado NOT IN ('resuelto', 'cancelado')) AS tickets,
                        (SELECT COUNT(*) FROM incidentes WHERE id_dispositivo = ? AND estado <> 'cerrado') AS incidentes";
                $consultaPendientes = $pdo->prepare($sqlPendientes);
                $consultaPendientes->execute([$idDispositivo, $idDispositivo]);
                $pendientes = $consultaPendientes->fetch();

                if ($pendientes['tickets'] || $pendientes['incidentes']) {
                    $mensaje = 'El equipo tiene tickets o incidentes pendientes. Finalizalos antes de darlo de baja.';
                }
            } else {
                $consultaUbicacion = $pdo->prepare('SELECT activo FROM ubicaciones WHERE id_ubicacion = ? FOR UPDATE');
                $consultaUbicacion->execute([$registro['id_ubicacion']]);

                if (!$consultaUbicacion->fetchColumn()) {
                    $mensaje = 'Editá el equipo y seleccioná una ubicación activa antes de reactivarlo.';
                }

                if ($registro['id_responsable']) {
                    $consultaResponsable = $pdo->prepare('SELECT activo FROM usuarios WHERE id_usuario = ? FOR UPDATE');
                    $consultaResponsable->execute([$registro['id_responsable']]);

                    if (!$consultaResponsable->fetchColumn()) {
                        $mensaje = 'Editá el equipo: su responsable está inactivo. Elegí otro o dejalo sin asignar.';
                    }
                }
            }

            if ($mensaje === '') {
                if ($accion === 'baja') {
                    $sqlCambio = "UPDATE dispositivos
                                  SET activo = 0, fecha_baja = NOW(), id_usuario_baja = ?, motivo_baja = ?
                                  WHERE id_dispositivo = ?";
                    $parametros = [$usuario['id_usuario'], $motivo, $idDispositivo];
                } else {
                    $sqlCambio = 'UPDATE dispositivos SET activo = 1 WHERE id_dispositivo = ?';
                    $parametros = [$idDispositivo];
                }

                $consultaCambio = $pdo->prepare($sqlCambio);
                $consultaCambio->execute($parametros);
                $pdo->commit();
                $_SESSION['mensaje_exito'] = 'Estado del dispositivo actualizado. Su historial se conservó.';
                header('Location: index.php?activo=todos');
                exit;
            }

            $pdo->rollBack();
        } catch (PDOException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensaje = errorGuardado($error, 'No se pudo actualizar el dispositivo.');
        }
    }
}

$titulo = $registro['activo'] ? 'Dar de baja dispositivo' : 'Reactivar dispositivo';
$nombreRegistro = $registro['codigo'];
$accionFormulario = $registro['activo'] ? 'baja' : 'reactivar';
$token = tokenFormulario();
require_once __DIR__ . '/../includes/encabezado.php';
require_once __DIR__ . '/../includes/confirmar_baja.php';
require_once __DIR__ . '/../includes/pie.php';
