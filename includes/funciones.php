<?php

//N: Bloques de seguridad para evitar ataques de inyeccion de codigo y proteger la aplicacion web y roles 

function escapar($texto) //* Sirve para escapar caracteres especiales en una cadena de texto, evitando vulnerabilidades de seguridad como XSS (codificacion o output encoding)
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8'); #convierte valor a texto, #ent_quotes convierte comillas simples y dobles a entidades, utf-8 es la codificacion 
}

function nombreRol($rol) //* Crea una lista (array) de roles 
{
    $nombresRoles = [        
        'administrador' => 'Administrador',
        'tecnico' => 'Técnico',
        'profesor' => 'Profesor'
    ];

    return $nombresRoles[$rol] ?? 'Sin rol'; #Devuelve el nombre del rol correspondiente de lo contrario devuelve 'Sin rol' xq no esta en la lista
}

function tokenFormulario()  //* Tokens antiCSRF: Genera un token por sesion para proteger los formularios de ataques CSRF (Cross-Site Request Forgery) y lo devuelve para ser incluido en el formulario como campo oculto
{
    if (empty($_SESSION['token'])) {                      #Se genera una sola vez mientras dure la sesion del usuario, sino se crea un token
        $_SESSION['token'] = bin2hex(random_bytes(32));   #produce token aleatorio de 32 bytes y lo convierte a hexadecimal, Y se almacena en la sesion del usuario
    }
    return $_SESSION['token'];                            #Devuelve el token generado para ser incluido en el formulario como campo oculto
}

function tokenValido() //* Valida el token recibido en el formulario con el almacenado en la sesion del usuario
{
    return isset($_SESSION['token'], $_POST['token'])        //? Verifica que existan ambos tokens, el de la sesion y el del formulario
        && is_string($_POST['token'])                        //? Verifica que el token recibido del formulario sea una cadena de texto
        && hash_equals($_SESSION['token'], $_POST['token']); //? Compara de manera segura ambos tokens, evitando ataques de temporización, y devuelve true si son iguales, false en caso contrario
}


//N: Bloques de validacion de datos de entrada del usuario y manejo de errores


function textoEntrada($datos, $campo, $valorInicial = '') //* Obtiene el valor de un campo que sea solamente texto del formulario por post o get
{
    if (!isset($datos[$campo]) || !is_string($datos[$campo])) {  #verifica que el campo exista y sea una cadena de texto, si no es asi devuelve el valor inicial
        return $valorInicial;
    }

    return trim($datos[$campo]); #Elimina espacios en blanco al inicio y al final del valor del campo y lo devuelve
}

function idEntrada($datos, $campo)  //* Obtiene el valor de un campo de identificador del formulario y lo valida como un entero positivo #Comprueba que tenga el formato esperado
{
    $valor = textoEntrada($datos, $campo); #obtiene el valor del campo de texto del formulario usando la funcion textoEntrada
    return filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null; //? Valida que el valor sea un entero positivo, si es valido lo devuelve, sino devuelve null
}

function errorGuardado($error, $mensajeDuplicado) //* Maneja errores de guardado en la base de datos, especialmente errores de duplicidad
{
    if ((int) ($error->errorInfo[1] ?? 0) === 1062) { //! Verifica si el codigo de error es 1062, que indica un error de duplicidad en la base de datos
        return $mensajeDuplicado; #devuelve el mensaje de error personalizado para duplicidad
    }

    return 'No se pudo guardar. Revisá los datos e intentá nuevamente.'; #devuelve un mensaje generico de error si no es un error de duplicidad
}