<?php

namespace Pairity\Console;

use Pairity\Migrations\MigrationGenerator;

class MakeMigrationCommand extends AbstractCommand
{
    public function execute(array $args): void
    {
        $name = $args[0] ?? null;
        if (!$name) {
            $this->stderr('Missing migration Name. Usage: pairity make:migration CreateUsersTable [--path=DIR]');
            exit(1);
        }

        $dir = $this->getMigrationsDir($args);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $generator = new MigrationGenerator($args['template'] ?? null);
        $file = $generator->generate($name, $dir);

        $this->stdout('Created: ' . $file);
    }
}
