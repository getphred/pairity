<?php

namespace Pairity\Tests\Migrations;

use PHPUnit\Framework\TestCase;
use Pairity\Migrations\MigrationGenerator;

class MigrationGeneratorTest extends TestCase
{
    public function testGeneratesFileInDirectory()
    {
        $dir = sys_get_temp_dir() . '/pairity_migrations_' . uniqid();
        mkdir($dir);
        
        $generator = new MigrationGenerator();
        $file = $generator->generate('CreateTestTable', $dir);
        
        $this->assertFileExists($file);
        $this->assertStringContainsString('CreateTestTable', $file);
        
        $content = file_get_contents($file);
        $this->assertStringContainsString('implements MigrationInterface', $content);
        
        unlink($file);
        rmdir($dir);
    }

    public function testUsesCustomTemplate()
    {
        $dir = sys_get_temp_dir() . '/pairity_migrations_' . uniqid();
        mkdir($dir);
        
        $template = "<?php // custom template";
        $generator = new MigrationGenerator($template);
        $file = $generator->generate('Custom', $dir);
        
        $this->assertEquals($template, file_get_contents($file));
        
        unlink($file);
        rmdir($dir);
    }
}
