<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Verificación local aislada; no importa la base definitiva.
$pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$nombre = 'schooldefend_prueba_' . bin2hex(random_bytes(6));
$creada = false;
try {
    $sql = file_get_contents(__DIR__ . '/../database/schooldefend.sql');
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
    $sql = str_replace(['CREATE DATABASE schooldefend ', 'USE schooldefend;'], ['CREATE DATABASE ' . $nombre . ' ', 'USE ' . $nombre . ';'], $sql);
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $sentencia) {
        if (trim($sentencia) === '') continue;
        $pdo->exec($sentencia);
        if (str_contains($sentencia, 'CREATE DATABASE ' . $nombre)) $creada = true;
    }
    $tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if (count($tablas) !== 6) throw new Exception('Cantidad inesperada de tablas.');
    foreach ($tablas as $tabla) {
        echo $tabla . ': ' . $pdo->query('SELECT COUNT(*) FROM `' . $tabla . '`')->fetchColumn() . PHP_EOL;
    }
    foreach ($pdo->query('SELECT password_hash FROM usuarios') as $usuario) {
        if (!password_verify('DemoEscolar2026!', $usuario['password_hash'])) throw new Exception('Hash inválido.');
    }
    $cantidad = $pdo->query('SELECT COUNT(*) FROM tickets t INNER JOIN dispositivos d ON d.id_dispositivo = t.id_dispositivo WHERE d.activo = 0')->fetchColumn();
    if ((int)$cantidad !== 1) throw new Exception('No se conserva el historial de baja.');
    $pdo->beginTransaction();
    try {
        $pdo->exec("INSERT INTO ubicaciones (nombre, id_usuario_baja) VALUES ('Prueba inválida', 999999)");
        throw new Exception('La clave foránea permitió un usuario inexistente.');
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1452) throw $e;
    } finally {
        $pdo->rollBack();
    }
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM dispositivos WHERE id_dispositivo = 5');
        throw new Exception('Se permitió borrar un equipo con historial.');
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) !== 1451) throw $e;
    } finally {
        $pdo->rollBack();
    }
    echo 'OK: importación, seis tablas, contraseñas, baja lógica y claves foráneas.' . PHP_EOL;
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($creada) {
        $pdo->exec('DROP DATABASE `' . $nombre . '`');
        echo 'Base temporal de prueba retirada.' . PHP_EOL;
    }
}

