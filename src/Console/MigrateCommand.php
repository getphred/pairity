<?php

namespace Pairity\Console;

use Pairity\Migrations\Migrator;
use Pairity\Migrations\MigrationLoader;

class MigrateCommand extends AbstractCommand
{
    public function execute(array $args): void
    {
        $conn = $this->getConnection($args);
        $dir = $this->getMigrationsDir($args);
        $migrations = MigrationLoader::fromDirectory($dir);
        
        if (!$migrations) {
            $this->stdout('No migrations found in ' . $dir);
            return;
        }

        $migrator = new Migrator($conn);
        $migrator->setRegistry($migrations);
        $pretend = isset($args['pretend']) && $args['pretend'];
        $result = $migrator->migrate($migrations, $pretend);

        if ($pretend) {
            $this->stdout('SQL to be executed:');
            foreach ($result as $log) {
                $this->stdout($log['sql']);
                if ($log['params']) {
                    $this->stdout('  Params: ' . json_encode($log['params']));
                }
            }
        } else {
            $this->stdout('Applied: ' . json_encode($result));
        }
    }
}
