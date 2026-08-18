<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EditionRegistrationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_edition_registrations_have_billing_columns_participations_do_not(): void
    {
        $this->assertTrue(Schema::hasTable('edition_registrations'));
        $this->assertTrue(Schema::hasColumn('edition_registrations', 'edition_division_id'));
        $this->assertTrue(Schema::hasColumn('edition_registrations', 'is_minor'));
        $this->assertTrue(Schema::hasColumn('edition_registrations', 'is_student'));
        $this->assertTrue(Schema::hasColumn('edition_registrations', 'discount'));
        $this->assertTrue(Schema::hasColumn('edition_registrations', 'payment'));

        $this->assertFalse(Schema::hasColumn('edition_participations', 'is_minor'));
        $this->assertFalse(Schema::hasColumn('edition_participations', 'is_student'));
        $this->assertFalse(Schema::hasColumn('edition_participations', 'discount'));
        $this->assertFalse(Schema::hasColumn('edition_participations', 'payment'));

        $column = collect(Schema::getColumns('edition_registrations'))
            ->firstWhere('name', 'edition_division_id');
        $this->assertNotNull($column);
        $this->assertTrue((bool) ($column['nullable'] ?? false));

        $unique = collect(Schema::getIndexes('edition_registrations'))->first(function (array $index): bool {
            $columns = $index['columns'] ?? [];

            return (bool) ($index['unique'] ?? false)
                && in_array('edition_division_id', $columns, true)
                && in_array('player_id', $columns, true);
        });
        $this->assertNotNull($unique);
        $this->assertStringContainsString('uq_registration_ed_player', $unique['name']);
    }
}
