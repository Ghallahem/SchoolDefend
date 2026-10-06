<?php

//N: Configuración local de XAMPP y captura de errores de conexión a la base de datos con PDO. Contiene datos del servidor.

function getConexion()    
{
    // Reutilizar la conexión dentro de la misma petición PHP.
    static $pdo = null; //* Guarda el objeto que representa la conexion porque static permite que la variable $pdo conserve su valor dentro de la misma peticion PHP

    if ($pdo !== null) { #si es distinto de null, significa que ya se ha creado la conexion y se devuelve el objeto existente
        return $pdo;
    }

    $servidor = '127.0.0.1';
    $nombreBaseDatos = 'schooldefend';
    $usuarioBaseDatos = 'root';
    $contrasenaBaseDatos = 'Nissan/Silvia/S15';

    try {          #intenta abrir la conexion con la base de datos, si falla, se captura la excepcion y se muestra un mensaje de error
        $pdo = new PDO(
            "mysql:host=$servidor;dbname=$nombreBaseDatos;charset=utf8mb4",       //n4: DSN (Data Source Name) que contiene la informacion de la conexion
            $usuarioBaseDatos,
            $contrasenaBaseDatos
        );

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);      //* Afecta errores de conexión y de consultas PDO.
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);    //* Define como devolver las filas de resultados de las consultas, en este caso como un array asociativo
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);#desactiva la emulacion de sentencias preparadas para usar las sentencias preparadas nativas de MySQL, lo que mejora la seguridad y el rendimiento

        $pdo->exec("SET time_zone = '-03:00'"); #exec ejecuta una sentencia SQL que establece la zona horaria

        return $pdo; #devuelve el objeto PDO que representa la conexion a la base de datos

    } catch (PDOException $error) {  #captura la excepcion si ocurre un error al intentar conectarse a la base de datos
        error_log($error->getMessage()); //! Registra el mensaje de error en el log de errores del servidor para su posterior análisis
        http_response_code(503); //! Responde con un codigo de estado HTTP 503 (Servicio no disponiblee)
        exit('No se pudo conectar con SchoolDefend. Revisá MySQL y los datos de includes/conexion.php.'); # (mensaje de error que se muestra al usuario)
    }
}