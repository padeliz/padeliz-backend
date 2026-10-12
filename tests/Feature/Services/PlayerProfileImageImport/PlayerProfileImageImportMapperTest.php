<?php

declare(strict_types=1);

namespace Tests\Feature\Services\PlayerProfileImageImport;

use App\Models\User;
use App\Services\PlayerProfileImageImport\PlayerProfileImageImportFilenameParser;
use App\Services\PlayerProfileImageImport\PlayerProfileImageImportMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlayerProfileImageImportMapperTest extends TestCase
{
    use RefreshDatabase;

    public function test_name_filename_matches_diacritic_player(): void
    {
        $player = User::factory()->create([
            'first_name' => 'Ștefan',
            'middle_name' => null,
            'last_name' => 'Popescu',
        ]);

        $mapper = new PlayerProfileImageImportMapper(new PlayerProfileImageImportFilenameParser());
        $mapped = $mapper->mapFilenames([
            ['index' => 0, 'filename' => 'Stefan Popescu.jpg'],
        ]);

        $this->assertSame('unmapped', $mapped[0]['mapping']['status']);
        $this->assertCount(1, $mapped[0]['mapping']['name_matches']);
        $this->assertSame($player->id_user, $mapped[0]['mapping']['name_matches'][0]->id_user);
    }
}
