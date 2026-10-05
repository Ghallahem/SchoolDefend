<?php
require_once __DIR__ . '/../includes/sesion.php';

$usuario = exigirSesion('../login.php');
exigirRoles($usuario, ['administrador']);
$pdo = getConexion();
$rutaBase = '../';
$idUbicacion = idEntrada($_GET, 'id');
$esEdicion = isset($_GET['id']);
$titulo = $esEdicion ? 'Editar ubicación' : 'Nueva ubicación';
$mensaje = '';
$ubicacion = ['nombre' => '', 'descripcion' => ''];

if ($esEdicion) {
    $consultaUbicacion = $pdo->prepare('SELECT nombre, descripcion FROM ubicaciones WHERE id_ubicacion = ?');
    $consultaUbicacion->execute([$idUbicacion]);
    $ubicacion = $consultaUbicacion->fetch();

    if (!$ubicacion) {
        http_response_code(404);
        exit('La ubicación no existe.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ubicacion['nombre'] = textoEntrada($_POST, 'nombre');
    $ubicacion['descripcion'] = textoEntrada($_POST, 'descripcion');

    if (!tokenValido()) {
        $mensaje = 'El formulario venció. Intentá nuevamente.';
    } elseif ($ubicacion['nombre'] === '' || mb_strlen($ubicacion['nombre']) > 100 || mb_strlen($ubicacion['descripcion']) > 255) {
        $mensaje = 'Ingresá un nombre de hasta 100 caracteres y una descripción de hasta 255.';
    } else {
        try {
            $parametros = [$ubicacion['nombre'], $ubicacion['descripcion'] ?: null];

            if ($esEdicion) {
                $sqlUbicacion = 'UPDATE ubicaciones SET nombre = ?, descripcion = ? WHERE id_ubicacion = ?';
                $parametros[] = $idUbicacion;
            } else {
                $sqlUbicacion = 'INSERT INTO ubicaciones (nombre, descripcion) VALUES (?, ?)';
            }

            $consultaGuardar = $pdo->prepare($sqlUbicacion);
            $consultaGuardar->execute($parametros);
            $_SESSION['mensaje_exito'] = 'Ubicación guardada correctamente.';
            header('Location: index.php');
            exit;
        } catch (PDOException $error) {
            $mensaje = errorGuardado($error, 'Ya existe una ubicación con ese nombre. Revisá también las inactivas.');
        }
    }
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
    <label for="nombre">Nombre</label>
    <input id="nombre" name="nombre" maxlength="100" value="<?= escapar($ubicacion['nombre']) ?>" required>
    <label for="descripcion">Descripción (opcional)</label>
    <textarea id="descripcion" name="descripcion" maxlength="255" rows="3"><?= escapar($ubicacion['descripcion']) ?></textarea>
    <div class="acciones">
        <button class="boton" type="submit">Guardar ubicación</button>
        <a class="boton boton-secundario" href="index.php">Cancelar</a>
    </div>
</form>
<?php require_once __DIR__ . '/../includes/pie.php'; ?>
