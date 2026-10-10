<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Models\Club;
use App\Models\Player;
use App\Traits\HasResourceMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasResourceMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_resource_name_returns_correct_plural_snake_case(): void
    {
        $this->assertEquals('players', Player::getResourceName());
        $this->assertEquals('clubs', Club::getResourceName());
    }

    public function test_get_searchable_fields_returns_defined_fields(): void
    {
        $expectedFields = [
            'first_name',
            'middle_name',
            'last_name',
        ];

        $this->assertEquals($expectedFields, Player::getSearchableFields());
    }

    public function test_get_searchable_fields_returns_empty_array_when_not_defined(): void
    {
        // Create a test model without searchable fields
        $testModel = new class () extends \Illuminate\Database\Eloquent\Model {
            use HasResourceMetadata;
        };

        $this->assertEquals([], $testModel::getSearchableFields());
    }

    public function test_get_filterable_fields_returns_defined_fields(): void
    {
        $expectedFields = [
            'user_types',
        ];

        $this->assertEquals($expectedFields, Player::getFilterableFields());
    }

    public function test_get_filterable_fields_returns_empty_array_when_not_defined(): void
    {
        // Create a test model without filterable fields
        $testModel = new class () extends \Illuminate\Database\Eloquent\Model {
            use HasResourceMetadata;
        };

        $this->assertEquals([], $testModel::getFilterableFields());
    }

    public function test_get_sortable_fields_returns_defined_fields(): void
    {
        $expectedFields = [
            'id_player',
            'first_name',
            'last_name',
            'inserted_at',
            'updated_at',
        ];

        $this->assertEquals($expectedFields, Player::getSortableFields());
    }

    public function test_get_sortable_fields_returns_empty_array_when_not_defined(): void
    {
        // Create a test model without sortable fields
        $testModel = new class () extends \Illuminate\Database\Eloquent\Model {
            use HasResourceMetadata;
        };

        $this->assertEquals([], $testModel::getSortableFields());
    }

    public function test_is_searchable_field_returns_true_for_valid_field(): void
    {
        $this->assertTrue(Player::isSearchableField('first_name'));
        $this->assertTrue(Player::isSearchableField('middle_name'));
        $this->assertTrue(Player::isSearchableField('last_name'));
    }

    public function test_is_searchable_field_returns_false_for_invalid_field(): void
    {
        $this->assertFalse(Player::isSearchableField('invalid_field'));
        $this->assertFalse(Player::isSearchableField('user_types'));
        $this->assertFalse(Player::isSearchableField('name'));
        $this->assertFalse(Player::isSearchableField(''));
    }

    public function test_is_filterable_field_returns_true_for_valid_field(): void
    {
        $this->assertTrue(Player::isFilterableField('user_types'));
    }

    public function test_is_filterable_field_returns_false_for_invalid_field(): void
    {
        $this->assertFalse(Player::isFilterableField('invalid_field'));
        $this->assertFalse(Player::isFilterableField('name'));
        $this->assertFalse(Player::isFilterableField(''));
    }

    public function test_is_sortable_field_returns_true_for_valid_field(): void
    {
        $this->assertTrue(Player::isSortableField('id_player'));
        $this->assertTrue(Player::isSortableField('first_name'));
        $this->assertTrue(Player::isSortableField('inserted_at'));
    }

    public function test_is_sortable_field_returns_false_for_invalid_field(): void
    {
        $this->assertFalse(Player::isSortableField('invalid_field'));
        $this->assertFalse(Player::isSortableField('nonexistent_field'));
        $this->assertFalse(Player::isSortableField('name'));
        $this->assertFalse(Player::isSortableField('middle_name'));
        $this->assertFalse(Player::isSortableField(''));
    }

    public function test_get_metadata_returns_complete_metadata_structure(): void
    {
        $metadata = Player::getMetadata();

        $this->assertArrayHasKey('resource', $metadata);
        $this->assertArrayHasKey('searchable_fields', $metadata);
        $this->assertArrayHasKey('filterable_fields', $metadata);
        $this->assertArrayHasKey('sortable_fields', $metadata);
        $this->assertArrayHasKey('list_fields', $metadata);

        $this->assertEquals('players', $metadata['resource']);
        $this->assertIsArray($metadata['searchable_fields']);
        $this->assertIsArray($metadata['filterable_fields']);
        $this->assertIsArray($metadata['sortable_fields']);
        $this->assertIsArray($metadata['list_fields']);
        $this->assertEquals([], $metadata['list_fields']);
    }

    public function test_get_list_fields_returns_empty_array_when_not_defined(): void
    {
        $this->assertEquals([], Player::getListFields());

        $testModel = new class () extends \Illuminate\Database\Eloquent\Model {
            use HasResourceMetadata;
        };

        $this->assertEquals([], $testModel::getListFields());
    }

    public function test_get_list_fields_returns_defined_fields(): void
    {
        $this->assertEquals(
            [
                'player.profile_image_url',
                'player_id',
                'event_division_id',
            ],
            \App\Models\EventRegistration::getListFields()
        );
        $this->assertEquals(['url'], \App\Models\Prize::getListFields());
    }

    public function test_get_metadata_returns_correct_values(): void
    {
        $metadata = Player::getMetadata();

        $this->assertEquals('players', $metadata['resource']);
        $this->assertEquals(Player::getSearchableFields(), $metadata['searchable_fields']);
        $this->assertEquals(Player::getFilterableFields(), $metadata['filterable_fields']);
        $this->assertEquals(Player::getSortableFields(), $metadata['sortable_fields']);
        $this->assertEquals(Player::getListFields(), $metadata['list_fields']);
    }

    public function test_get_metadata_works_with_model_without_fields(): void
    {
        // Create a test model without any field definitions
        $testModel = new class () extends \Illuminate\Database\Eloquent\Model {
            use HasResourceMetadata;
        };

        $metadata = $testModel::getMetadata();

        $this->assertArrayHasKey('resource', $metadata);
        $this->assertArrayHasKey('searchable_fields', $metadata);
        $this->assertArrayHasKey('filterable_fields', $metadata);
        $this->assertArrayHasKey('sortable_fields', $metadata);
        $this->assertArrayHasKey('list_fields', $metadata);

        $this->assertEquals([], $metadata['searchable_fields']);
        $this->assertEquals([], $metadata['filterable_fields']);
        $this->assertEquals([], $metadata['sortable_fields']);
        $this->assertEquals([], $metadata['list_fields']);
    }

    public function test_club_model_metadata_works_correctly(): void
    {
        $this->assertEquals('clubs', Club::getResourceName());

        $expectedSearchableFields = ['name', 'city'];
        $this->assertEquals($expectedSearchableFields, Club::getSearchableFields());

        $expectedFilterableFields = ['city'];
        $this->assertEquals($expectedFilterableFields, Club::getFilterableFields());

        $expectedSortableFields = ['id_club', 'name', 'city', 'inserted_at', 'updated_at'];
        $this->assertEquals($expectedSortableFields, Club::getSortableFields());

        // Test field checking
        $this->assertTrue(Club::isSearchableField('name'));
        $this->assertTrue(Club::isFilterableField('city'));
        $this->assertTrue(Club::isSortableField('name'));

        $this->assertFalse(Club::isSearchableField('invalid_field'));
        $this->assertFalse(Club::isFilterableField('invalid_field'));
        $this->assertFalse(Club::isSortableField('invalid_field'));
    }

    public function test_division_model_metadata_works_correctly(): void
    {
        $this->assertEquals('divisions', \App\Models\Division::getResourceName());

        $expectedSearchableFields = ['name'];
        $this->assertEquals($expectedSearchableFields, \App\Models\Division::getSearchableFields());

        $expectedFilterableFields = ['its_first_event_id'];
        $this->assertEquals($expectedFilterableFields, \App\Models\Division::getFilterableFields());

        $expectedSortableFields = ['id_division', 'name', 'color', 'its_first_event_id', 'inserted_at', 'updated_at'];
        $this->assertEquals($expectedSortableFields, \App\Models\Division::getSortableFields());

        // Test field checking
        $this->assertTrue(\App\Models\Division::isSearchableField('name'));
        $this->assertTrue(\App\Models\Division::isFilterableField('its_first_event_id'));
        $this->assertTrue(\App\Models\Division::isSortableField('name'));

        $this->assertFalse(\App\Models\Division::isSearchableField('invalid_field'));
        $this->assertFalse(\App\Models\Division::isFilterableField('invalid_field'));
        $this->assertFalse(\App\Models\Division::isSortableField('invalid_field'));
    }

    public function test_prize_model_metadata_works_correctly(): void
    {
        $this->assertEquals('prizes', \App\Models\Prize::getResourceName());

        $expectedSearchableFields = ['name', 'box_1_text', 'box_2_text'];
        $this->assertEquals($expectedSearchableFields, \App\Models\Prize::getSearchableFields());

        $expectedFilterableFields = ['template', 'box_1_bg_color', 'box_1_text_color', 'box_2_bg_color', 'box_2_text_color'];
        $this->assertEquals($expectedFilterableFields, \App\Models\Prize::getFilterableFields());

        $expectedSortableFields = ['id_prize', 'name', 'template', 'inserted_at', 'updated_at'];
        $this->assertEquals($expectedSortableFields, \App\Models\Prize::getSortableFields());

        // Test field checking
        $this->assertTrue(\App\Models\Prize::isSearchableField('name'));
        $this->assertTrue(\App\Models\Prize::isSearchableField('box_1_text'));
        $this->assertTrue(\App\Models\Prize::isSearchableField('box_2_text'));
        $this->assertTrue(\App\Models\Prize::isFilterableField('box_1_bg_color'));
        $this->assertTrue(\App\Models\Prize::isSortableField('name'));

        $this->assertFalse(\App\Models\Prize::isSearchableField('invalid_field'));
        $this->assertFalse(\App\Models\Prize::isFilterableField('invalid_field'));
        $this->assertFalse(\App\Models\Prize::isSortableField('invalid_field'));
    }

    public function test_case_sensitivity_in_field_checking(): void
    {
        // Test that field checking is case-sensitive
        $this->assertTrue(Player::isSearchableField('first_name'));
        $this->assertFalse(Player::isSearchableField('First_Name'));
        $this->assertFalse(Player::isSearchableField('FIRST_NAME'));
        $this->assertFalse(Player::isSearchableField('name'));
        $this->assertFalse(Player::isSortableField('name'));

        $this->assertTrue(Player::isFilterableField('user_types'));
        $this->assertFalse(Player::isFilterableField('User_Types'));
        $this->assertFalse(Player::isFilterableField('USER_TYPES'));
    }

    public function test_empty_string_handling(): void
    {
        // Test that empty strings are handled correctly
        $this->assertFalse(Player::isSearchableField(''));
        $this->assertFalse(Player::isFilterableField(''));
        $this->assertFalse(Player::isSortableField(''));
    }

    public function test_null_handling(): void
    {
        // Test that null values are handled correctly
        // Note: These methods expect string parameters, so null should be cast to string
        $this->assertFalse(Player::isSearchableField((string) null));
        $this->assertFalse(Player::isFilterableField((string) null));
        $this->assertFalse(Player::isSortableField((string) null));
    }
}
