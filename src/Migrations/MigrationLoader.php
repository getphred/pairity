<?php

namespace Pairity\Migrations;

final class MigrationLoader
{
    /**
     * Load migrations from a directory.
     * Each PHP file should return a MigrationInterface instance or define a class that implements it (autoloadable).
     *
     * @return array<string,MigrationInterface> Ordered map name => instance
     */
    public static function fromDirectory(string $dir): array
    {
        $result = [];
        if (!is_dir($dir)) {
            return $result;
        }
        $files = glob(rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $loaded = require $file;
            if ($loaded instanceof MigrationInterface) {
                $result[$name] = $loaded;
                continue;
            }
            // If file didn't return an instance but defines a class with the same basename, try to instantiate.
            if (class_exists($name)) {
                $obj = new $name();
                if ($obj instanceof MigrationInterface) {
                    $result[$name] = $obj;
                }
            }
        }
        return $result;
    }
}
