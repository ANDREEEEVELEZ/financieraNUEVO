<?php

/**
 * SDD core-contable-seguridad, Slice D, Requisito 4.2.
 *
 * Grep-guard: cero referencias a `detallesPago` (el alias de compatibilidad
 * eliminado de Pago.php) en app/ y resources/. Deliberadamente framework-free
 * (sin Tests\TestCase / sin DB): asserciones puras sobre el contenido de los
 * archivos, corre incluso si la base de datos de pruebas no está disponible.
 */
function archivosPhpEnDirectorio(string $directorio): array
{
    if (! is_dir($directorio)) {
        return [];
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directorio, FilesystemIterator::SKIP_DOTS)
    );

    $archivos = [];
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $archivos[] = $file->getPathname();
        }
    }

    return $archivos;
}

it('no quedan referencias a detallesPago en app/', function () {
    $base = dirname(__DIR__, 3).'/app';

    foreach (archivosPhpEnDirectorio($base) as $archivo) {
        $contenido = file_get_contents($archivo);
        expect($contenido)->not->toContain('detallesPago', "Referencia a detallesPago encontrada en {$archivo}");
    }
});

it('no quedan referencias a detallesPago en resources/', function () {
    $base = dirname(__DIR__, 3).'/resources';

    foreach (archivosPhpEnDirectorio($base) as $archivo) {
        $contenido = file_get_contents($archivo);
        expect($contenido)->not->toContain('detallesPago', "Referencia a detallesPago encontrada en {$archivo}");
    }
});

it('Pago model expone aplicacionesPago pero ya no detallesPago', function () {
    expect(method_exists(\App\Models\Pago::class, 'aplicacionesPago'))->toBeTrue();
    expect(method_exists(\App\Models\Pago::class, 'detallesPago'))->toBeFalse();
});
