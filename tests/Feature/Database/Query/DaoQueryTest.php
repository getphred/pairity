<?php

declare(strict_types=1);

namespace Pairity\Tests\Feature\Database\Query;

use Pairity\Database\Connection;
use Pairity\Database\Drivers\SQLiteDriver;
use Pairity\Database\DatabaseManager;
use Pairity\Database\Query\Builder;
use Pairity\Database\Query\Paginator;
use Pairity\DAO\BaseDAO;
use Pairity\DTO\BaseDTO;
use Pairity\DTO\IdentityMap;
use PHPUnit\Framework\TestCase;

class UserFeatureDTO extends BaseDTO {
    public $id;
    public $name;
    public $email;
    public function __construct(array $attributes = []) {
        parent::__construct($attributes);
        $this->id = $attributes['id'] ?? null;
        $this->name = $attributes['name'] ?? null;
        $this->email = $attributes['email'] ?? null;
    }
}

class UserFeatureDAO extends BaseDAO {
    protected string $table = 'users';
    protected ?string $dtoClass = UserFeatureDTO::class;
    
    public function scopeActive($builder) {
        return $builder->where('status', 'active');
    }
}

class DaoQueryTest extends TestCase
{
    protected $db;
    protected $dao;

    protected function setUp(): void
    {
        $config = [
            'default' => 'default',
            'connections' => [
                'default' => [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                ],
            ],
        ];
        $this->db = new DatabaseManager($config);
        $this->db->connection()->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, status TEXT)');
        
        $identityMap = new IdentityMap();
        $this->dao = new UserFeatureDAO($this->db, $identityMap);
    }

    public function test_it_executes_queries_via_dao()
    {
        $this->db->connection()->execute('INSERT INTO users (name, email, status) VALUES (?, ?, ?)', ['Alice', 'alice@example.com', 'active']);
        $this->db->connection()->execute('INSERT INTO users (name, email, status) VALUES (?, ?, ?)', ['Bob', 'bob@example.com', 'inactive']);

        $users = $this->dao->query()->get();
        $this->assertCount(2, $users);
        $this->assertInstanceOf(UserFeatureDTO::class, $users[0]);
        $this->assertEquals('Alice', $users[0]->name);
    }

    public function test_it_uses_scopes()
    {
        $this->db->connection()->execute('INSERT INTO users (name, email, status) VALUES (?, ?, ?)', ['Alice', 'alice@example.com', 'active']);
        $this->db->connection()->execute('INSERT INTO users (name, email, status) VALUES (?, ?, ?)', ['Bob', 'bob@example.com', 'inactive']);

        $activeUsers = $this->dao->query()->active()->get();
        $this->assertCount(1, $activeUsers);
        $this->assertEquals('Alice', $activeUsers[0]->name);
    }

    public function test_it_paginates_results()
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->db->connection()->execute('INSERT INTO users (name, email, status) VALUES (?, ?, ?)', ["User $i", "user$i@example.com", 'active']);
        }

        $paginator = $this->dao->query()->paginate(5, 2);

        $this->assertInstanceOf(Paginator::class, $paginator);
        $this->assertEquals(20, $paginator->total());
        $this->assertCount(5, $paginator->items());
        $this->assertEquals(2, $paginator->currentPage());
        $this->assertEquals(4, $paginator->lastPage());
        $this->assertEquals('User 6', $paginator->items()[0]->name);
    }
}
