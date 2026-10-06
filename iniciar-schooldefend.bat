@echo off
setlocal

:: Ruta común de AppServ (ajustar si instalaste en otro disco)
set "PHP_APPSERV=C:\AppServ\php7\php.exe"
set "PUERTO=8086"
set "RUTA_PROYECTO=%~dp0"

if not exist "%PHP_APPSERV%" (
    echo No se encontro PHP en %PHP_APPSERV%.
    echo Revisa que AppServ este instalado en C:\AppServ.
    pause
    exit /b 1
)

echo.
echo SchoolDefend se abrira en http://127.0.0.1:%PUERTO%/login.php
echo Apache y MySQL deben estar iniciados desde el panel de AppServ.
echo Para detener la pagina, presiona Ctrl+C en esta ventana.
echo.

start "" "http://127.0.0.1:%PUERTO%/login.php"
"%PHP_APPSERV%" -S 127.0.0.1:%PUERTO% -t "%RUTA_PROYECTO%"

if errorlevel 1 (
    echo.
    echo No se pudo iniciar SchoolDefend. Es posible que el puerto %PUERTO% ya este ocupado.
    pause
)

endlocal
