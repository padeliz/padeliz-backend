<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventRegistrationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_registrations_have_billing_columns_participations_do_not(): void
    {
        $this->assertTrue(Schema::hasTable('event_registrations'));
        $this->assertTrue(Schema::hasColumn('event_registrations', 'event_division_id'));
        $this->assertTrue(Schema::hasColumn('event_registrations', 'is_minor'));
        $this->assertTrue(Schema::hasColumn('event_registrations', 'is_student'));
        $this->assertFalse(Schema::hasColumn('event_registrations', 'discount'));
        $this->assertTrue(Schema::hasColumn('event_registrations', 'payment'));
        $this->assertTrue(Schema::hasColumn('event_registrations', 'payment_due_at'));

        $this->assertFalse(Schema::hasColumn('event_participations', 'is_minor'));
        $this->assertFalse(Schema::hasColumn('event_participations', 'is_student'));
        $this->assertFalse(Schema::hasColumn('event_participations', 'discount'));
        $this->assertFalse(Schema::hasColumn('event_participations', 'payment'));

        $column = collect(Schema::getColumns('event_registrations'))
            ->firstWhere('name', 'event_division_id');
        $this->assertNotNull($column);
        $this->assertFalse((bool) ($column['nullable'] ?? true));

        $this->assertFalse(Schema::hasColumn('events', 'entry_fee'));
        $this->assertFalse(Schema::hasColumn('events', 'students_entry_fee'));
        $this->assertTrue(Schema::hasColumn('event_divisions', 'entry_fee'));

        $entryFeeColumn = collect(Schema::getColumns('event_divisions'))
            ->firstWhere('name', 'entry_fee');
        $this->assertNotNull($entryFeeColumn);
        $this->assertFalse((bool) ($entryFeeColumn['nullable'] ?? true));
        $default = $entryFeeColumn['default'] ?? null;
        $this->assertTrue(
            $default === null || $default === '',
            'event_divisions.entry_fee must have no DB default'
        );

        $activeDivisionUnique = collect(Schema::getIndexes('event_registrations'))->first(function (array $index): bool {
            $columns = $index['columns'] ?? [];

            return (bool) ($index['unique'] ?? false)
                && in_array('event_division_id', $columns, true)
                && in_array('active_player_user_for_division', $columns, true);
        });
        $this->assertNotNull($activeDivisionUnique);
    }
}
