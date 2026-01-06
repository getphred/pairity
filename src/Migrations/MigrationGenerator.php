<?php

namespace Pairity\Migrations;

use Pairity\Contracts\ConnectionInterface;

class MigrationGenerator
{
    private string $template;

    public function __construct(?string $template = null)
    {
        $this->template = $template ?? $this->defaultTemplate();
    }

    public function generate(string $name, string $directory): string
    {
        $ts = date('Y_m_d_His');
        $filename = $directory . DIRECTORY_SEPARATOR . $ts . '_' . $name . '.php';
        
        file_put_contents($filename, $this->template);
        
        return $filename;
    }

    private function defaultTemplate(): string
    {
        return <<<'PHP'
<?php

use Pairity\Migrations\MigrationInterface;
use Pairity\Contracts\ConnectionInterface;
use Pairity\Schema\SchemaManager;
use Pairity\Schema\Blueprint;

return new class implements MigrationInterface {
    public function up(ConnectionInterface $connection): void
    {
        // Example: create table
        // $schema = SchemaManager::forConnection($connection);
        // $schema->create('example', function (Blueprint $t) {
        //     $t->increments('id');
        //     $t->string('name', 255);
        // });
    }

    public function down(ConnectionInterface $connection): void
    {
        // Example: drop table
        // $schema = SchemaManager::forConnection($connection);
        // $schema->dropIfExists('example');
    }
};
PHP;
    }
}
