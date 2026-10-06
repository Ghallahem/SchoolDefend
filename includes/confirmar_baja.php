<?php
//N: Bloque 1: adaptar el formulario compartido al estado actual del registro

//?: $registro y las demás variables son preparadas por el archivo baja.php que incluye esta vista.
if ($registro['activo']) {
    $textoBoton = 'Confirmar baja';
} else {
    $textoBoton = 'Confirmar reactivación';
}
?>

<?php //N: Bloque 2: mostrar el registro y sus antecedentes de baja. ?>
<h1><?= escapar($titulo) ?></h1>
<p>Registro: <strong><?= escapar($nombreRegistro) ?></strong>.</p>
<p class="texto-secundario">Se conservarán sus datos y relaciones anteriores.</p>

<?php if ($registro['fecha_baja']): ?>
    <?php //N3: La información recuperada de la base se codifica antes de insertarla en HTML. ?>
    <p>Última baja: <?= escapar($registro['fecha_baja']) ?>. Motivo: <?= escapar($registro['motivo_baja']) ?></p>
<?php endif; ?>

<?php if ($mensaje !== ''): ?>
    <?php //!: Presenta un error controlado sin imprimir detalles internos de la base de datos. ?>
    <p class="alerta" role="alert"><?= escapar($mensaje) ?></p>
<?php endif; ?>

<?php //N: Bloque 3: enviar la confirmación de baja o reactivación mediante POST. ?>
<form method="post" class="formulario formulario-edicion">
    <?php //!: Los campos hidden no son secretos ni confiables; baja.php debe validar el token y la acción recibida. ?>
    <input type="hidden" name="token" value="<?= escapar($token) ?>">
    <input type="hidden" name="accion" value="<?= escapar($accionFormulario) ?>">

    <?php if ($registro['activo']): ?>
        <?php //N3: Al dar de baja un registro activo se exige un motivo; al reactivarlo este campo no aparece. ?>
        <label for="motivo">Motivo de la baja</label>
        <?php //?: maxlength y required ayudan en el navegador, pero baja.php también debe validar el dato porque el cliente puede alterarlos. ?>
        <textarea id="motivo" name="motivo" maxlength="255" rows="3" required><?= escapar($motivo) ?></textarea>
    <?php endif; ?>

    <div class="acciones">
        <?php //N2: El botón confirma por POST; el enlace Cancelar abandona la operación sin modificar el registro. ?>
        <button class="boton" type="submit"><?= escapar($textoBoton) ?></button>
        <a class="boton boton-secundario" href="<?= escapar($rutaCancelar ?? 'index.php?activo=todos') ?>">Cancelar</a>
    </div>
</form>
