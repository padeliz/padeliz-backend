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

        $activeDivisionUnique = collect(Schema::getIndexes('event_registrations'))->first(function (array $index): bool {
            $columns = $index['columns'] ?? [];

            return (bool) ($index['unique'] ?? false)
                && in_array('event_division_id', $columns, true)
                && in_array('active_player_for_division', $columns, true);
        });
        $this->assertNotNull($activeDivisionUnique);
    }
}
