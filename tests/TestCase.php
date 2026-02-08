<?php

declare(strict_types=1);

namespace Pairity\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Class TestCase
 *
 * Base test class for Pairity. Provides common setup and utility methods
 * for all unit and feature tests.
 *
 * @package Pairity\Tests
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * This method is called before each test.
     * Use it for shared setup logic if needed.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
    }
}
