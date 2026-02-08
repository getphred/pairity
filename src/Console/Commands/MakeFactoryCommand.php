<?php

declare(strict_types=1);

namespace Pairity\Console\Commands;

use Pairity\Contracts\Console\CommandInterface;
use Pairity\Contracts\Database\DatabaseManagerInterface;
use Pairity\Contracts\Translation\TranslatorInterface;

/**
 * Class MakeFactoryCommand
 *
 * CLI command to create a new factory class.
 */
class MakeFactoryCommand implements CommandInterface
{
    /**
     * MakeFactoryCommand constructor.
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
        return 'make:factory';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return $this->translator->trans('command.make_factory.description', 'Create a new factory class for a model.');
    }

    /**
     * @inheritDoc
     */
    public function execute(array $args, array $options): int
    {
        $name = $args[0] ?? null;

        if (!$name) {
            echo "Error: Factory name is required (e.g., UserFactory).\n";
            return 1;
        }

        $path = 'src/Database/Factories/' . $name . '.php';

        if (file_exists($path)) {
            echo "Error: Factory [{$name}] already exists.\n";
            return 1;
        }

        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $modelName = str_replace('Factory', '', $name);
        $dtoFqcn = 'App\\Models\\DTO\\' . $modelName . 'DTO';
        $daoFqcn = 'App\\Models\\DAO\\' . $modelName . 'DAO';

        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Database\\Factories;\n\nuse Pairity\\Database\\Factories\\Factory;\nuse {$dtoFqcn};\nuse {$daoFqcn};\n\nclass {$name} extends Factory\n{\n    /**\n     * Define the model's default state.\n     *\n     * @return array<string, mixed>\n     */\n    public function definition(): array\n    {\n        return [\n            // 'name' => 'John Doe',\n        ];\n    }\n\n    /**\n     * @inheritDoc\n     */\n    public function model(): string\n    {\n        return {$modelName}DTO::class;\n    }\n\n    /**\n     * @inheritDoc\n     */\n    public function dao(): string\n    {\n        return {$modelName}DAO::class;\n    }\n}\n";

        file_put_contents($path, $content);

        echo "Factory created successfully at [{$path}].\n";

        return 0;
    }
}
