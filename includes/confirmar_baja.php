<?php
// $registro viene del archivo que incluye este formulario.
if ($registro['activo']) {
    $textoBoton = 'Confirmar baja';
} else {
    $textoBoton = 'Confirmar reactivación';
}
?>

<h1><?= escapar($titulo) ?></h1>
<p>Registro: <strong><?= escapar($nombreRegistro) ?></strong>.</p>
<p class="texto-secundario">Se conservarán sus datos y relaciones anteriores.</p>

<?php if ($registro['fecha_baja']): ?>
    <p>Última baja: <?= escapar($registro['fecha_baja']) ?>. Motivo: <?= escapar($registro['motivo_baja']) ?></p>
<?php endif; ?>

<?php if ($mensaje !== ''): ?>
    <p class="alerta" role="alert"><?= escapar($mensaje) ?></p>
<?php endif; ?>

<form method="post" class="formulario formulario-edicion">
    <input type="hidden" name="token" value="<?= escapar($token) ?>">
    <input type="hidden" name="accion" value="<?= escapar($accionFormulario) ?>">

    <?php if ($registro['activo']): ?>
        <label for="motivo">Motivo de la baja</label>
        <textarea id="motivo" name="motivo" maxlength="255" rows="3" required><?= escapar($motivo) ?></textarea>
    <?php endif; ?>

    <div class="acciones">
        <button class="boton" type="submit"><?= escapar($textoBoton) ?></button>
        <a class="boton boton-secundario" href="<?= escapar($rutaCancelar ?? 'index.php?activo=todos') ?>">Cancelar</a>
    </div>

</form>
