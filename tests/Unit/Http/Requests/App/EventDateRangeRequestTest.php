<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\App;

use App\Http\Requests\App\EventDateRangeRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class EventDateRangeRequestTest extends TestCase
{
    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return (new EventDateRangeRequest())->rules();
    }

    public function test_accepts_valid_inclusive_range(): void
    {
        $validator = Validator::make([
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
        ], $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_accepts_same_day_as_inclusive_range(): void
    {
        $validator = Validator::make([
            'date_from' => '2026-04-12',
            'date_to' => '2026-04-12',
        ], $this->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_rejects_when_date_from_missing(): void
    {
        $validator = Validator::make([
            'date_to' => '2026-04-30',
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('date_from', $validator->errors()->toArray());
    }

    public function test_rejects_when_date_to_missing(): void
    {
        $validator = Validator::make([
            'date_from' => '2026-04-01',
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('date_to', $validator->errors()->toArray());
    }

    public function test_rejects_invalid_date_format(): void
    {
        $validator = Validator::make([
            'date_from' => '12/04/2026',
            'date_to' => '2026-04-30',
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('date_from', $validator->errors()->toArray());
    }

    public function test_rejects_when_date_to_is_before_date_from(): void
    {
        $validator = Validator::make([
            'date_from' => '2026-04-10',
            'date_to' => '2026-04-09',
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('date_to', $validator->errors()->toArray());
    }

    public function test_accepts_sort_asc_and_desc(): void
    {
        foreach (['asc', 'desc'] as $sort) {
            $validator = Validator::make([
                'date_from' => '2026-04-01',
                'date_to' => '2026-04-30',
                'sort' => $sort,
            ], $this->rules());

            $this->assertTrue($validator->passes(), "sort={$sort} should pass");
        }
    }

    public function test_rejects_invalid_sort(): void
    {
        $validator = Validator::make([
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
            'sort' => 'sideways',
        ], $this->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('sort', $validator->errors()->toArray());
    }
}
