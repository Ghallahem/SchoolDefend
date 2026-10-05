<?php
// Configuración local de XAMPP. Cambiar estos datos si cambia el servidor.
function getConexion()
{
    // Reutilizar la conexión dentro de la misma petición PHP.
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $servidor = '127.0.0.1';
    $nombre_BD = 'schooldefend';
    $usuario_BD = 'root';
    $contrasena_BD = 'Nissan/Silvia/S15';

    try {
        $pdo = new PDO(
            "mysql:host=$servidor;dbname=$nombre_BD;charset=utf8mb4",
            $usuario_BD,
            $contrasena_BD
        );

        //establecer atributos para el PDO.
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
        // Si error: lanzar PDOException.
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); 
        // fetch() devuelve un array asosiativo (diccionario en python)
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); 
        // [falso] >>> los prepare statements se procesan en el servidor (por seguridad) 
        $pdo->exec("SET time_zone = '-03:00'"); 
        // Ejecuta una consutla en SQL, para establecer zona horario UTC-3 (zona de Argentina)

        return $pdo;
    } catch (PDOException $error) {
        http_response_code(503);
        exit('No se pudo conectar con SchoolDefend. Revisá MySQL y los datos de includes/conexion.php.');
    }
}
