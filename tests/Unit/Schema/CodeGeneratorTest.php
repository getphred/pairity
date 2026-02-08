<?php

declare(strict_types=1);

namespace Pairity\Tests\Unit\Schema;

use Pairity\Schema\CodeGenerator;
use Pairity\Schema\Blueprint;
use Pairity\Schema\Column;
use PHPUnit\Framework\TestCase;

class CodeGeneratorTest extends TestCase
{
    protected string $stubsPath;

    protected function setUp(): void
    {
        $this->stubsPath = __DIR__ . '/../../../src/Stubs';
    }

    public function test_it_generates_dto_code()
    {
        $blueprint = new Blueprint('users');
        $blueprint->addColumn('id', 'bigInteger', ['primary' => true]);
        $blueprint->addColumn('email', 'string', ['unique' => true]);

        $generator = new CodeGenerator($this->stubsPath);
        $code = $generator->generateDto($blueprint, 'App\Models\DTO');

        $this->assertStringContainsString('namespace App\Models\DTO;', $code);
        $this->assertStringContainsString('class UsersDTO extends BaseDTO', $code);
        $this->assertStringContainsString('protected $id;', $code);
        $this->assertStringContainsString('protected $email;', $code);
        $this->assertStringContainsString('public function getId()', $code);
        $this->assertStringContainsString('public function getEmail()', $code);
    }

    public function test_it_generates_dao_code()
    {
        $blueprint = new Blueprint('users');
        $blueprint->addColumn('id', 'bigInteger', ['primary' => true]);

        $generator = new CodeGenerator($this->stubsPath);
        $code = $generator->generateDao($blueprint, 'App\Models\DAO', 'App\Models\DTO');

        $this->assertStringContainsString('namespace App\Models\DAO;', $code);
        $this->assertStringContainsString('class UsersDAO extends BaseDAO', $code);
        $this->assertStringContainsString("protected string \$table = 'users';", $code);
        $this->assertStringContainsString("protected string \$primaryKey = 'id';", $code);
        $this->assertStringContainsString('use App\Models\DTO\UsersDTO;', $code);
    }
}
