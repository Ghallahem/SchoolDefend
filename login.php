<?php

//N: Bloque 1: cargar la sesion y detectar un usuario conectado

require_once __DIR__ . '/includes/sesion.php';   //n2: Al cargar sesion ya cargamos conexion y funciones 

$usuarioConectado = usuarioActual();      //n2: Busca el ID guardado en la sesión, consulta al usuario en MySQL y verifica que siga activo y tenga un rol válido

if ($usuarioConectado !== null) {        //n2: si enceuentra un usuario conectado, redirige a index.php en vez del ingreso
    header('Location: index.php');
    exit;
}

//N: Bloque 2: asegurar que haya una sesion abierta 

if (session_status() !== PHP_SESSION_ACTIVE) {       //*: indica el estado de las sesiones, si no hay sesion activa, la inicia
    session_start();
}

//N: Bloque 3: procesar el formulario de login

$mensaje = '';
$nombreUsuario = '';
$contrasena = '';
$rolesPermitidos = ['administrador', 'tecnico', 'profesor'];

//N: Bloque 4: procesar el formulario de login si se envio por POST

if ($_SERVER['REQUEST_METHOD'] === 'POST') {                                      //n2: Verifica si el formulario se envió mediante el método POST

    //N: Bloque 5: leer usuario y contraseña del formulario

    if (isset($_POST['nombre_usuario']) && is_string($_POST['nombre_usuario'])) { //n*: Verifica que el campo nombre_usuario exista y sea una cadena de texto
        $nombreUsuario = trim($_POST['nombre_usuario']);                          //n*: Elimina espacios en blanco al inicio y al final del nombre de usuario
    }
    if (isset($_POST['password']) && is_string($_POST['password'])) {             //n2: Verifica que el campo password exista y sea una cadena de texto
        $contrasena = $_POST['password'];                                         //n2: Obtiene la contraseña tal como fue ingresada, sin modificarla
    }

    //N: Bloque 6: validar el token CSRF, usuario y contraseña

    if (!tokenValido()) {                                                //*: Verifica que el token CSRF enviado en el formulario coincida con el almacenado en la sesión del usuario
        $mensaje = 'El formulario venció. Intentá ingresar nuevamente.';          
    } elseif ($nombreUsuario === '' || $contrasena === '') {             //*: Verifica que el nombre de usuario y la contraseña no estén vacíos
        $mensaje = 'Completá el usuario y la contraseña.';
    } else {                                                                      
        $pdo = getConexion();                                            //*: Obtiene la conexión PDO a la base de datos para ejecutar consultas SQL de manera segura

        //N: Bloque 7: Construir y ejecutar la consulta SQL para obtener el usuario de la base de datos

        //n4: Consultas MYSQL seleccionando esas columnas dentro de la tabla usuarios donde haya un valor en nombre de usuario guardada en la variable esa
        $sqlUsuario = "
            SELECT id_usuario, password_hash, activo, rol 
            FROM usuarios
            WHERE nombre_usuario = ?";

        //N: Bloque 8: Preparar y ejecutar la consulta SQL, y verificar si el usuario existe y cumple con los requisitos de acceso

        $consultaUsuario = $pdo->prepare($sqlUsuario); //n4: Prepara la consulta SQL, evitando inyecciones SQL y mejorando el rendimiento al reutilizar la consulta preparada.
        $consultaUsuario->execute([$nombreUsuario]);   //n4: Ejecuta la consulta SQL, obteniendo los datos del usuario correspondiente de la base de datos.
        $cuenta = $consultaUsuario->fetch();           //n4: Obtiene la primera fila de resultados de la consulta como un array asociativo, que contiene los datos

        $accesoPermitido = false;                     //n4: Inicializa la variable de control de acceso como falso, indicando que el acceso no está permitido por defecto.

        if ($cuenta) {                                                                       //?: Solo ejectuca si fetch encontro un usuario
            $cuentaActiva = (int) $cuenta['activo'] === 1;                                  //?: Comprueba que la cuenta esta activa
            $rolPermitido = in_array($cuenta['rol'], $rolesPermitidos, true);              //?: Comprueba que el rol del usuario este en la lista de roles permitidos
            $contrasenaCorrecta = password_verify($contrasena, $cuenta['password_hash']); //?: Verifica que la contraseña ingresada coincida con el hash almacenado en la db, utilizando la función password_verify

            $accesoPermitido = $cuentaActiva && $rolPermitido && $contrasenaCorrecta;   //?: Exige que la cuenta este activa, el rol sea permitido y la contraseña sea correcta para permitir el acceso
        }

        //N: Bloque 9: Crear una sesion autenticada

        if ($accesoPermitido) {             
            session_regenerate_id(true);  //?: PHP genera un nuevo ID de sesión para prevenir ataques de fijación de sesión, y elimina la sesión anterior para mayor seguridad.

            $_SESSION = [];                                          //?: Limpia datos de sesión previos, eliminando cualquier información almacenada anteriormente.
            $_SESSION['id_usuario'] = (int) $cuenta['id_usuario'];   //?: Almacena el ID del usuario autenticado en la sesión, permitiendo identificar al usuario en futuras solicitudes.

            header('Location: index.php');    //?: Redirige al usuario a la página principal del sistema después de iniciar sesión correctamente.
            exit;
        }

        $mensaje = 'Usuario o contraseña incorrectos, o cuenta inactiva.';   //!: Mensaje de error genérico para no revelar información sensible sobre la cuenta o el estado de la misma.
    }
}

//N: Bloque 10: mostrar el formulario de login

$titulo = 'Iniciar sesión'; 
$usuario = null; 
$mensajeParaMostrar = escapar($mensaje); //*: Escapa los caracteres especiales del mensaje de error 
$nombreUsuarioParaMostrar = escapar($nombreUsuario); //*: Escapa los caracteres especiales del nombre de usuario
$tokenAcceso = escapar(tokenFormulario()); //*: Genera un token CSRF para proteger el formulario de ataques 

require_once __DIR__ . '/includes/encabezado.php'; //N3: Carga el encabezado HTML de la página, incluyendo el título y los estilos necesarios para mostrar el formulario de login.
?>                                                  
<section class="acceso">
    <p class="etiqueta">Acceso al sistema</p>

    <h1>Iniciar sesión</h1>

    <p class="texto-secundario">Ingresá con tu cuenta para acceder a las funciones de tu rol.</p>
   
    <?php //n3: Muestra el mensaje de error si no es una cadena vacía, indicando que hubo un problema con el inicio de sesión ?>
    <?php if ($mensajeParaMostrar !== ''): ?>
        <?php //n3: Muestra el mensaje de error con role "alert" para que los lectores de pantalla lo anuncien como una alerta ?>
        <p class="alerta" role="alert"><?= $mensajeParaMostrar ?></p>
    <?php endif; ?>

    <?php //n3: Abre el formulario de inicio de sesión, enviando los datos mediante POST a la misma página para su procesamiento ?>
    <form method="post" action="login.php" class="formulario">

        <?php //n3: Enviar el token CSRF como campo oculto para proteger el formulario ?>
        <input type="hidden" name="token" value="<?= $tokenAcceso ?>">
        <label for="nombre_usuario">Usuario</label>                  

        <?php //n3: Campo de entrada para el nombre de usuario ?>
        <?php //n3: Rellena el campo con el nombre de usuario ingresado previamente, si lo hay, y establece el foco en este campo al cargar la págin ?>
        <input id="nombre_usuario" name="nombre_usuario" maxlength="50" autocomplete="username"
               value="<?= $nombreUsuarioParaMostrar ?>" required autofocus>

        <?php //n3: Campo de entrada para la contraseña, con tipo "password" para ocultar los caracteres ingresados ?>
        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required> 

        <?php //n3: Botón para enviar el formulario y procesar el inicio de sesión ?>
        <button class="boton" type="submit">Ingresar</button>
    </form>

    <?php //n3: Mensaje de ayuda indicando que las cuentas deben ser creadas por un administrador del sistema ?>
    <p class="ayuda">Las cuentas son creadas por el Administrador.</p>
</section>
<?php //n1: incluye el pie para completar el documento, incluyendo las etiquetas de cierre correspondientes.} ?>
<?php require_once __DIR__ . '/includes/pie.php'; ?>
