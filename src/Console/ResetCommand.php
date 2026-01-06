<?php

namespace Pairity\Console;

use Pairity\Migrations\Migrator;
use Pairity\Migrations\MigrationLoader;

class ResetCommand extends AbstractCommand
{
    public function execute(array $args): void
    {
        $conn = $this->getConnection($args);
        $dir = $this->getMigrationsDir($args);
        $migrations = MigrationLoader::fromDirectory($dir);
        
        $migrator = new Migrator($conn);
        $migrator->setRegistry($migrations);
        
        $pretend = isset($args['pretend']) && $args['pretend'];
        $totalResult = [];

        while (true) {
            $result = $migrator->rollback(1, $pretend);
            if (!$result) {
                break;
            }
            $totalResult = array_merge($totalResult, $result);
        }

        if ($pretend) {
            $this->stdout('SQL to be executed:');
            foreach ($totalResult as $log) {
                $this->stdout($log['sql']);
                if ($log['params']) {
                    $this->stdout('  Params: ' . json_encode($log['params']));
                }
            }
        } else {
            $this->stdout('Reset complete. Rolled back: ' . json_encode($totalResult));
        }
    }
}
