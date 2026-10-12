<?php

declare(strict_types=1);

namespace Tests\Feature\Traits;

use App\Models\Club;
use App\Models\User;
use App\Traits\HasResourceMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasResourceMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_resource_name_returns_correct_plural_snake_case(): void
    {
        $this->assertEquals('players', User::getResourceName());
        $this->assertEquals('clubs', Club::getResourceName());
    }

    public function test_get_searchable_fields_returns_defined_fields(): void
    {
        $expectedFields = [
            'first_name',
            'middle_name',
            'last_name',
        ];

        $this->assertEquals($expectedFields, User::getSearchableFields());
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

        $this->assertEquals($expectedFields, User::getFilterableFields());
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
            'id_user',
            'first_name',
            'last_name',
            'inserted_at',
            'updated_at',
        ];

        $this->assertEquals($expectedFields, User::getSortableFields());
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
        $this->assertTrue(User::isSearchableField('first_name'));
        $this->assertTrue(User::isSearchableField('middle_name'));
        $this->assertTrue(User::isSearchableField('last_name'));
    }

    public function test_is_searchable_field_returns_false_for_invalid_field(): void
    {
        $this->assertFalse(User::isSearchableField('invalid_field'));
        $this->assertFalse(User::isSearchableField('user_types'));
        $this->assertFalse(User::isSearchableField('name'));
        $this->assertFalse(User::isSearchableField(''));
    }

    public function test_is_filterable_field_returns_true_for_valid_field(): void
    {
        $this->assertTrue(User::isFilterableField('user_types'));
    }

    public function test_is_filterable_field_returns_false_for_invalid_field(): void
    {
        $this->assertFalse(User::isFilterableField('invalid_field'));
        $this->assertFalse(User::isFilterableField('name'));
        $this->assertFalse(User::isFilterableField(''));
    }

    public function test_is_sortable_field_returns_true_for_valid_field(): void
    {
        $this->assertTrue(User::isSortableField('id_user'));
        $this->assertTrue(User::isSortableField('first_name'));
        $this->assertTrue(User::isSortableField('inserted_at'));
    }

    public function test_is_sortable_field_returns_false_for_invalid_field(): void
    {
        $this->assertFalse(User::isSortableField('invalid_field'));
        $this->assertFalse(User::isSortableField('nonexistent_field'));
        $this->assertFalse(User::isSortableField('name'));
        $this->assertFalse(User::isSortableField('middle_name'));
        $this->assertFalse(User::isSortableField(''));
    }

    public function test_get_metadata_returns_complete_metadata_structure(): void
    {
        $metadata = User::getMetadata();

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
        $this->assertEquals([], User::getListFields());

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
                'player_user_id',
                'event_division_id',
            ],
            \App\Models\EventRegistration::getListFields()
        );
        $this->assertEquals(['url'], \App\Models\Prize::getListFields());
    }

    public function test_get_metadata_returns_correct_values(): void
    {
        $metadata = User::getMetadata();

        $this->assertEquals('players', $metadata['resource']);
        $this->assertEquals(User::getSearchableFields(), $metadata['searchable_fields']);
        $this->assertEquals(User::getFilterableFields(), $metadata['filterable_fields']);
        $this->assertEquals(User::getSortableFields(), $metadata['sortable_fields']);
        $this->assertEquals(User::getListFields(), $metadata['list_fields']);
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
        $this->assertTrue(User::isSearchableField('first_name'));
        $this->assertFalse(User::isSearchableField('First_Name'));
        $this->assertFalse(User::isSearchableField('FIRST_NAME'));
        $this->assertFalse(User::isSearchableField('name'));
        $this->assertFalse(User::isSortableField('name'));

        $this->assertTrue(User::isFilterableField('user_types'));
        $this->assertFalse(User::isFilterableField('User_Types'));
        $this->assertFalse(User::isFilterableField('USER_TYPES'));
    }

    public function test_empty_string_handling(): void
    {
        // Test that empty strings are handled correctly
        $this->assertFalse(User::isSearchableField(''));
        $this->assertFalse(User::isFilterableField(''));
        $this->assertFalse(User::isSortableField(''));
    }

    public function test_null_handling(): void
    {
        // Test that null values are handled correctly
        // Note: These methods expect string parameters, so null should be cast to string
        $this->assertFalse(User::isSearchableField((string) null));
        $this->assertFalse(User::isFilterableField((string) null));
        $this->assertFalse(User::isSortableField((string) null));
    }
}
