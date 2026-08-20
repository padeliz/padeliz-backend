<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PlayerProfileImageImport;

use App\Services\PlayerProfileImageImport\PlayerProfileImageImportFilenameParser;
use PHPUnit\Framework\TestCase;

class PlayerProfileImageImportFilenameParserTest extends TestCase
{
    private PlayerProfileImageImportFilenameParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new PlayerProfileImageImportFilenameParser();
    }

    public function test_classifies_email_stem(): void
    {
        $result = $this->parser->parse('mara@example.com.jpg');
        $this->assertSame('email', $result['type']);
        $this->assertSame('mara@example.com', $result['stem']);
    }

    public function test_classifies_phone_stem(): void
    {
        $result = $this->parser->parse('+40712345678.png');
        $this->assertSame('phone', $result['type']);
    }

    public function test_classifies_numeric_id_stem(): void
    {
        $result = $this->parser->parse('42.webp');
        $this->assertSame('id', $result['type']);
        $this->assertSame('42', $result['stem']);
    }

    public function test_classifies_name_stem(): void
    {
        $result = $this->parser->parse('Mara Popescu.jpg');
        $this->assertSame('name', $result['type']);
    }
}
