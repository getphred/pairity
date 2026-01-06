<?php

namespace Pairity\Console;

use Pairity\Migrations\MigrationLoader;
use Pairity\Migrations\MigrationsRepository;

class StatusCommand extends AbstractCommand
{
    public function execute(array $args): void
    {
        $conn = $this->getConnection($args);
        $dir = $this->getMigrationsDir($args);
        $migrations = array_keys(MigrationLoader::fromDirectory($dir));
        
        $repo = new MigrationsRepository($conn);
        $ran = $repo->getRan();
        $pending = array_values(array_diff($migrations, $ran));
        
        $this->stdout('Ran: ' . json_encode($ran));
        $this->stdout('Pending: ' . json_encode($pending));
    }
}
