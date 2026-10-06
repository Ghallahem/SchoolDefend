# SchoolDefend

Aplicación web académica con PHP puro, PDO, MySQL, HTML y CSS.

## Etapa actual

Implementados conexión PDO, inicio y cierre de sesión, resumen por rol y gestión de usuarios, ubicaciones y dispositivos. Los tres módulos permiten alta, edición, baja lógica y reactivación. El inventario incluye filtros y una ficha con tickets e incidentes anteriores. También está implementado el módulo de tickets: creación, consulta, asignación, prioridad, estados y seguimiento. Las reparaciones en talleres se anotan como novedades dentro del seguimiento del ticket, sin módulo separado. Incidentes incluye registro manual, consulta, filtros, asignación, investigación y cierre. Informes incluye vistas imprimibles de tickets, incidentes e inventario. La exportación se realiza con guardar como PDF del navegador; no se genera un archivo PDF desde PHP. Revisión funcional final completada; el usuario confirmó la presentación y los filtros de los informes. Ver la guía de entrega en docs/guia_entrega.md.

Administrador puede gestionar los tres módulos; Técnico puede gestionar dispositivos; Profesor puede crear tickets y consultar únicamente sus reportes. Administrador y Técnico pueden atender todos los tickets. Los permisos también se verifican al entrar directamente a una URL o enviar un formulario.

## Probar los nuevos módulos

1. Ingresar como `admin` y abrir **Ubicaciones**. Crear un sector de prueba.
2. En **Usuarios**, crear una cuenta de prueba o revisar las existentes.
3. En **Dispositivos**, registrar un equipo con código único y asignarlo al sector creado.
4. Editarlo y abrir **Ver** para consultar su ficha.
5. Darlo de baja indicando un motivo; encontrarlo con el filtro **Inactivos** y reactivarlo.
6. Intentar dar de baja Laboratorio 1 o un equipo con trabajos pendientes: debe explicar por qué no lo permite.

No hace falta volver a importar SQL: se utiliza el esquema existente. Las altas que hagas desde la aplicación sí se guardan en tu base.

Cada módulo usa `index.php` para listar, `formulario.php` para alta/edición y `baja.php` para confirmar baja o reactivación. Dispositivos agrega `detalle.php`. Se comparte el formulario de alta/edición para no repetir los mismos campos y validaciones; no hay un generador de módulos ni clases de CRUD.

## Abrir la aplicación

La base schooldefend ya fue importada en este equipo. No volver a importar el SQL para probar las pantallas.

1. Mantener MySQL de XAMPP iniciado.
2. Desde una terminal en esta carpeta ejecutar:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8086 -t .
```

3. Abrir http://127.0.0.1:8086/login.php en el navegador.
4. Mantener la terminal abierta; Ctrl+C detiene el servidor. Si el puerto ya está ocupado por SchoolDefend, usar el servidor existente.

Esta opción permite trabajar directamente en la carpeta actual, sin copiar el proyecto a htdocs. No utiliza Apache: el servidor incorporado de PHP atiende las páginas y MySQL de XAMPP guarda los datos. Más adelante puede ejecutarse desde Apache colocando el proyecto en su carpeta de sitios.

## Cuentas ficticias

Usuarios: `admin`, `tecnico`, `profesor`.
Contraseña de los tres: `DemoEscolar2026!`.

## Archivos

- `includes/conexion.php`: datos locales de conexión y función getConexion.
- `login.php`: formulario y validación de credenciales.
- `logout.php`: cierre por POST.
- `index.php`: resumen y últimos tickets según el rol.
- `includes/sesion.php`: inicio de sesión PHP, comprobación de cuenta activa y funciones de autorización.
- `includes/funciones.php`: escape de texto, nombres de roles y token de formularios.
- `includes/encabezado.php` y `pie.php`: HTML compartido.
- `css/estilos.css`: diseño común.
- `database/`: SQL e instrucciones de importación.
- `docs/modelo_datos.md`: tablas y reglas.
- `.local/`: verificaciones de desarrollo; no forma parte de las pantallas.

Las páginas de módulos llaman exigirSesion y exigirRoles antes de consultar o modificar información. No alcanza con esconder botones. Para páginas dentro de una subcarpeta se indicará `../login.php` a exigirSesion y `$rutaBase = '../'` antes de incluir el encabezado.

## Verificación de esta etapa

Se revisó la sintaxis de los PHP y se probaron mediante HTTP las tres cuentas, contraseña incorrecta, formulario sin token, acceso sin sesión, cierre válido y cierre inválido. Se verificó que el Profesor no recibe un ticket de otro creador ni los indicadores de incidentes. Se revisó visualmente el formulario de acceso en el navegador.

Los datos del práctico y los registros de SchoolDefend no se modificaron durante estas pruebas de acceso. La carpeta de sesiones de XAMPP debe permitir escritura al proceso PHP.

Para los nuevos módulos se realizaron pruebas HTTP en una copia temporal de la aplicación y una base ficticia independiente: alta, edición, duplicados, permisos, motivo de baja, protección del último Administrador, dependencias pendientes, filtros y reactivación con relaciones activas. También se comprobó que un equipo dado de baja conserva su historial. La copia y la base temporal se retiraron al finalizar; las cuentas, ubicaciones y equipos de la base definitiva se conservaron. Se revisaron visualmente el listado y el formulario del inventario en el navegador.

## Estilo del código

Se sigue el equilibrio del ejemplo del usuario y del práctico grupal: PHP de procesamiento al inicio y HTML debajo. Las consultas largas se dividen en líneas; las cortas pueden ocupar una línea. Los contadores de una misma tabla se agrupan con COUNT y SUM en una sola consulta. La conexión PDO se reutiliza dentro de cada petición.

Las etiquetas cortas y su contenido van en una línea; los contenedores y bloques se indentan tradicionalmente. La vista solo imprime datos (escapándolos cuando corresponde), itera y usa condicionales simples. Las consultas, validaciones y preparación de arrays van arriba. La conexión, sesión, encabezado y pie se comparten desde includes. Se usan nombres descriptivos, sin clases complejas, frameworks, ORM ni librerías externas.

## Probar tickets

1. Ingresar como `profesor`, abrir **Tickets** y registrar un problema. Elegir una ubicación y, si corresponde, un equipo de ese mismo lugar.
2. Cerrar sesión e ingresar como `tecnico` o `admin`.
3. Abrir el reporte y elegir **Gestionar ticket**. Asignar un Técnico o Administrador y pasar a **En proceso**.
4. Registrar novedades. Para **En espera de repuesto**, explicar qué falta; para continuar, volver a **En proceso**.
5. Para resolver, completar diagnóstico y solución. Para cancelar, indicar el motivo. Los finalizados se conservan y no se reabren en esta versión.
6. Volver como Profesor y consultar el resultado y el seguimiento del reporte propio.

La prioridad inicial es media y el creador se toma de la sesión. No se cambia el texto original, la ubicación ni el equipo durante la atención. Las novedades se agregan sin editar las anteriores. Resolver un ticket no cambia automáticamente el estado del dispositivo ni cierra incidentes relacionados.

Si se lleva un equipo a un taller, el ticket permanece En proceso y se agrega una novedad indicando el motivo. El regreso se registra con otra novedad.
Archivos del módulo: `tickets/index.php`, `insertar.php`, `detalle.php`, `editar.php` y `opciones.php`. PHP de procesamiento arriba y HTML debajo, sin frameworks ni librerías. Se usa el esquema ya importado; no hay que ejecutar SQL adicional.

Las pruebas temporales incluyen el ciclo completo, filtros, reporte sin equipo, equipo de otra ubicación o inactivo, campos manipulados de creador/estado/prioridad, cierre sin diagnóstico, cancelación sin motivo, responsable sin rol técnico, protección de reportes ajenos y conservación del historial. También se verificaron nuevamente los módulos anteriores. Las pruebas no modifican schooldefend.

### Probar incidentes de seguridad

1. Ingresar como tecnico o admin y abrir Incidentes en el menú.
2. Registrar un incidente con título, tipo, descripción, severidad y responsable. Equipo y ticket son opcionales; si ambos identifican equipo, deben coincidir.
3. Abrir su detalle y Gestionar incidente. Pasar a En investigación y completar acciones realizadas.
4. Cerrar con acciones y conclusión completas. La fecha de cierre se guarda automáticamente.
5. Consultar el cerrado usando los filtros. Los cerrados ya no se editan; el reporte original y sus relaciones se conservan.

Administrador y Técnico gestionan incidentes. Profesor no accede, tampoco por URL directa. Las acciones son un texto editable durante la atención, no un historial de versiones. Este registro no modifica automáticamente el estado del ticket ni del equipo relacionado. Se verificaron permisos, validaciones y el flujo completo mediante pruebas HTTP en una base temporal. No es necesario reimportar SQL.

### Simplificación de talleres

Se retiraron los formularios y tablas de servicios y derivaciones. En Gestionar ticket → Novedad / observaciones podés escribir: «Se llevó a reparar a un taller». El equipo puede marcarse manualmente En reparación desde el inventario. Al regresar se agrega otra novedad; luego se resuelve el ticket cuando se comprueba la solución.

La base local ya tiene seis tablas. Las reparaciones anteriores se conservan como notas del seguimiento. El esquema schooldefend.sql es solo para instalaciones nuevas; no debe reimportarse sobre la base existente. Se retiraron los scripts temporales y respaldos del módulo eliminado.

### Informes e impresión

Administrador y Técnico pueden abrir Informes, elegir Tickets, Incidentes o Inventario y aplicar filtros. Tickets e incidentes se filtran por fecha de creación (incluye completo el día final) y estado. Inventario se filtra por estado técnico y activo/inactivo. El total corresponde a las filas filtradas, sin una consulta de conteo adicional.

Para guardar: Ctrl + P → Guardar como PDF. Se recomienda A4 horizontal; desactivar los encabezados y pies propios del navegador si aparecen URL o fecha adicional. CSS oculta los controles y usa fondo blanco. No hay biblioteca PDF, JavaScript, framework ni cambios de base. Si el docente exige un archivo PDF generado directamente por PHP, esta opción de impresión deberá revisarse con él.

Verificado: sintaxis PHP y pruebas HTTP de permisos, filtros, fechas inválidas y conservación de equipos inactivos. El usuario confirmó que el informe se ve bien y aplica los filtros correctamente. Para listados más largos, revisar la vista previa antes de guardar.

## Entrega y exposición

Consultar [Guía de entrega](docs/guia_entrega.md): instalación en otra computadora, roles, recorrido de demostración y explicación del código. La revisión final incluyó sintaxis de 33 archivos PHP, esquema de seis tablas y pruebas funcionales de los módulos. Se completó el uso de la variable textoBoton en confirmar_baja.php; el resto del flujo se conserva.
