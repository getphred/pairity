<?php

declare(strict_types=1);

namespace Pairity\DTO;

use Pairity\DAO\BaseDAO;
use RuntimeException;

/**
 * Class ProxyFactory
 *
 * Generates and instantiates lazy-loading proxy classes for DTOs.
 */
class ProxyFactory
{
    /**
     * @var array<string, string> Cache of generated proxy class names.
     */
    protected static array $proxyClasses = [];

    /**
     * Create a new proxy instance.
     *
     * @param string $dtoClass
     * @param BaseDAO $dao
     * @param mixed $id
     * @return object
     */
    public function create(string $dtoClass, BaseDAO $dao, mixed $id): object
    {
        $proxyClass = $this->getProxyClass($dtoClass);

        return new $proxyClass($dao, $id);
    }

    /**
     * Get or generate the proxy class name for a DTO.
     *
     * @param string $dtoClass
     * @return string
     */
    protected function getProxyClass(string $dtoClass): string
    {
        if (isset(self::$proxyClasses[$dtoClass])) {
            return self::$proxyClasses[$dtoClass];
        }

        $proxyClassName = 'Pairity_Proxy_' . md5($dtoClass);
        
        if (!class_exists($proxyClassName)) {
            $this->generateProxyClass($dtoClass, $proxyClassName);
        }

        return self::$proxyClasses[$dtoClass] = $proxyClassName;
    }

    /**
     * Generate the proxy class code and eval it.
     *
     * @param string $dtoClass
     * @param string $proxyClassName
     * @return void
     */
    protected function generateProxyClass(string $dtoClass, string $proxyClassName): void
    {
        $code = <<<PHP
class {$proxyClassName} extends \\{$dtoClass} implements \\Pairity\\DTO\\ProxyInterface
{
    protected bool \$__initialized = false;
    protected \\Pairity\\DAO\\BaseDAO \$__dao;
    protected \$__id;

    public function __construct(\\Pairity\\DAO\\BaseDAO \$dao, \$id)
    {
        parent::__construct(['id' => \$id]);
        \$this->__dao = \$dao;
        \$this->__id = \$id;
    }

    public function __load(): void
    {
        if (\$this->__initialized) return;
        
        \$this->__initialized = true;
        \$fullDto = \$this->__dao->find(\$this->__id);
        
        if (\$fullDto) {
            foreach (\$fullDto->toArray() as \$key => \$value) {
                \$this->\$key = \$value;
            }
        }
    }

    public function __isInitialized(): bool
    {
        return \$this->__initialized;
    }
}
PHP;
        eval($code);
    }
}
