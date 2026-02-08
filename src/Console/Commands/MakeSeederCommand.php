<?php

declare(strict_types=1);

namespace Pairity\Console\Commands;

use Pairity\Contracts\Console\CommandInterface;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\Contracts\Translation\TranslatorInterface;

/**
 * Class MakeSeederCommand
 *
 * CLI command to create a new seeder class.
 */
class MakeSeederCommand implements CommandInterface
{
    /**
     * MakeSeederCommand constructor.
     *
     * @param DatabaseManagerInterface $db
     * @param TranslatorInterface $translator
     */
    public function __construct(
        protected DatabaseManagerInterface $db,
        protected TranslatorInterface $translator
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'make:seeder';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return $this->translator->trans('command.make_seeder.description', 'Create a new seeder class.');
    }

    /**
     * @inheritDoc
     */
    public function execute(array $args, array $options): int
    {
        $name = $args[0] ?? null;

        if (!$name) {
            echo "Error: Seeder name is required.\n";
            return 1;
        }

        $path = 'src/Database/Seeds/' . $name . '.php';

        if (file_exists($path)) {
            echo "Error: Seeder [{$name}] already exists.\n";
            return 1;
        }

        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Database\\Seeds;\n\nuse Pairity\\Database\\Seeding\\Seeder;\n\nclass {$name} extends Seeder\n{\n    /**\n     * Run the database seeds.\n     *\n     * @return void\n     */\n    public function run(): void\n    {\n        // \$this->call(SomeOtherSeeder::class);\n    }\n}\n";

        file_put_contents($path, $content);

        echo "Seeder created successfully at [{$path}].\n";

        return 0;
    }
}
