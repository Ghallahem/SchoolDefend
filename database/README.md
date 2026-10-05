# Importar SchoolDefend desde phpMyAdmin

## Archivo preparado

`database/schooldefend.sql` crea la base `schooldefend`, sus seis tablas y los datos ficticios. No es necesario crear tablas ni relaciones a mano. Tampoco hace falta crear la base antes: el archivo incluye CREATE DATABASE y USE.

El archivo es para una instalación nueva, una sola vez. No borra bases ni tablas existentes. Si ya existe una base llamada schooldefend, no importar sobre ella ni eliminarla: revisar primero su contenido. Si una importación falla, conservar el mensaje de error y revisar antes de repetirla; las tablas creadas pueden permanecer aunque falle la carga de datos.

## Pasos

1. En el panel de XAMPP, iniciar Apache y MySQL si no están activos.
2. Abrir phpMyAdmin desde el botón Admin de MySQL. En una instalación con puertos predeterminados suele estar en `http://localhost/phpmyadmin/`.
3. Ir al inicio de phpMyAdmin, en el nivel del servidor, sin seleccionar una base del práctico.
4. Entrar a **Importar**, seleccionar `database/schooldefend.sql` y mantener formato SQL y codificación UTF-8.
5. Pulsar **Importar** o **Continuar**, según la versión.
6. Al terminar, abrir `schooldefend` en el panel izquierdo. Si no aparece, actualizar el panel.
7. Comprobar que aparecen las seis tablas de la lista siguiente.

Si la cuenta de phpMyAdmin no tiene permiso para crear bases, se necesitará una cuenta con ese permiso; no se debe elegir una base del práctico como sustituto.

## Datos esperados

| Tabla | Registros |
|---|---:|
| usuarios | 3 |
| ubicaciones | 3 |
| dispositivos | 5 |
| tickets | 5 |
| seguimiento_tickets | 9 |
| incidentes | 2 |

Los casos incluyen un ticket sin equipo, una investigación de seguridad, notas sobre reparaciones en talleres y un dispositivo inactivo con un ticket conservado.

## Cuentas de demostración

| Usuario | Rol |
|---|---|
| admin | Administrador |
| tecnico | Técnico |
| profesor | Profesor |

Contraseña de las tres cuentas: `DemoEscolar2026!`.

Son credenciales ficticias para la demostración local. La columna password_hash contiene hashes generados y verificados con PHP. Estas cuentas permiten entrar al login de la aplicación una vez iniciado el servidor PHP. No son credenciales para acceder a phpMyAdmin.

## Verificación realizada

El 27 de septiembre de 2026 se importó el SQL en una base temporal aislada usando el servidor local de XAMPP (MariaDB 10.4.32). Se comprobaron las seis tablas, los recuentos, los hashes de las tres contraseñas, la conservación del ticket de un equipo dado de baja y el rechazo de relaciones inexistentes y del borrado de un equipo con historial. La base temporal se retiró al finalizar.

La verificación del esquema usa una base temporal y no modifica schooldefend ni las bases del práctico. La prueba se ejecutó mediante PDO, no mediante la interfaz de phpMyAdmin. En este XAMPP el servicio rotulado MySQL utiliza MariaDB; la aceptación de ese motor como equivalente para el trabajo depende del requisito del docente. El SQL usa construcciones comunes a MySQL y MariaDB, pero todavía no se ejecutó en un servidor MySQL independiente.

Las reglas de permisos, cierres y responsables activos están implementadas en PHP. Las tablas y las claves foráneas por sí solas no reemplazan esas validaciones.

## Base existente simplificada

La base local ya tiene seis tablas y conserva el seguimiento de los tickets. No reimportar schooldefend.sql. Los scripts temporales de conversión y sus respaldos se retiraron tras verificar la actualización.
