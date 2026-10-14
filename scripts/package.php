<?php

/** Source delivery: tracked files, compiled assets and a release manifest. */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
if (! extension_loaded('zip')) {
    throw new RuntimeException('Se requiere la extensión PHP zip.');
}
$root = dirname(__DIR__);
$version = trim(file_get_contents($root.'/VERSION'));
if (! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
    throw new RuntimeException('VERSION debe contener una versión semántica válida.');
}
function gitOutput(array $arguments, string $root): string
{
    $process = proc_open(['git', ...$arguments], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    if (! is_resource($process)) {
        throw new RuntimeException('Git no está disponible. Empaqueta desde un checkout versionado.');
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('No se pudo consultar Git: '.$error);
    }

    return $output;
}
if (trim(gitOutput(['status', '--porcelain'], $root)) !== '') {
    throw new RuntimeException('Registra los cambios del proyecto antes de crear una entrega.');
}
if (! is_file($root.'/public/build/manifest.json')) {
    throw new RuntimeException('Ejecuta npm run build antes de empaquetar.');
}
$files = array_filter(explode("\0", gitOutput(['ls-files', '-z'], $root)));
$assets = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/public/build', FilesystemIterator::SKIP_DOTS));
foreach ($assets as $asset) {
    if ($asset->isFile()) {
        $files[] = str_replace('\\', '/', substr($asset->getPathname(), strlen($root) + 1));
    }
}
$files = array_values(array_unique($files));
sort($files);
$directory = $root.'/dist';
if (! is_dir($directory) && ! mkdir($directory, 0755, true)) {
    throw new RuntimeException('No se pudo crear dist.');
}
$target = $directory.'/SmartAulaUES_v'.$version.'_fase1.zip';
$zip = new ZipArchive;
if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('No se pudo crear el ZIP.');
}
$hashes = [];
try {
    foreach ($files as $file) {
        if (preg_match('~^(?:\.git/|vendor/|node_modules/|dist/|backup/|\.phpunit)|^\.env(?:\.|$)|^storage/(?:logs/|app/private/|framework/)|^bootstrap/cache/.*\.php$|^public/(?:hot$|storage/)~', $file)
            && ! in_array($file, ['.env.example', '.env.testing.example']) && basename($file) !== '.gitignore') {
            throw new RuntimeException('Archivo privado o generado inesperado: '.$file);
        }
        $path = $root.'/'.$file;
        if (is_link($path) || ! is_file($path)) {
            throw new RuntimeException('Archivo ausente o enlace inesperado: '.$file);
        }
        if (! $zip->addFile($path, 'SmartAulaUES/'.$file)) {
            throw new RuntimeException('No se pudo incluir: '.$file);
        }
        $hashes[$file] = hash_file('sha256', $path);
    }
    $zip->addFromString('SmartAulaUES/RELEASE.json', json_encode([
        'application' => 'SmartAulaUES', 'version' => $version, 'phase' => 1,
        'source_commit' => trim(gitOutput(['rev-parse', 'HEAD'], $root)),
        'base_commit' => '446396cb61353fbd391faab0078babe908e43980',
        'repository' => 'https://github.com/Josu1202/SmartAulaUES.git',
        'published_to_github' => false, 'files_sha256' => $hashes,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
} catch (Throwable $exception) {
    $zip->close();
    unlink($target);
    throw $exception;
}
if (! $zip->close()) {
    throw new RuntimeException('No se pudo finalizar el ZIP.');
}
file_put_contents($target.'.sha256', hash_file('sha256', $target).'  '.basename($target).PHP_EOL);
fwrite(STDOUT, $target.PHP_EOL);
