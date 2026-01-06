<?php

namespace Pairity\Console;

use Pairity\Migrations\Migrator;
use Pairity\Migrations\MigrationLoader;

class RollbackCommand extends AbstractCommand
{
    public function execute(array $args): void
    {
        $conn = $this->getConnection($args);
        $dir = $this->getMigrationsDir($args);
        $migrations = MigrationLoader::fromDirectory($dir);
        
        $migrator = new Migrator($conn);
        $migrator->setRegistry($migrations);
        
        $steps = isset($args['steps']) ? max(1, (int)$args['steps']) : 1;
        $pretend = isset($args['pretend']) && $args['pretend'];
        
        $result = $migrator->rollback($steps, $pretend);

        if ($pretend) {
            $this->stdout('SQL to be executed:');
            foreach ($result as $log) {
                $this->stdout($log['sql']);
                if ($log['params']) {
                    $this->stdout('  Params: ' . json_encode($log['params']));
                }
            }
        } else {
            $this->stdout('Rolled back: ' . json_encode($result));
        }
    }
}
