<?php

declare(strict_types=1);

namespace Tests\Feature\Services\PlayerCustomField;

use App\Enums\PlayerCustomFieldType;
use App\Enums\PlayerCustomFieldValueReviewStatus;
use App\Models\PadelEvent;
use App\Models\User;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldOption;
use App\Models\PlayerCustomFieldValue;
use App\Models\PlayerCustomFieldValueOption;
use App\Services\PlayerCustomField\PlayerCustomFieldParticipationResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Support\UnixEventFactory;

final class PlayerCustomFieldParticipationResolverTest extends TestCase
{
    use RefreshDatabase;

    private PlayerCustomFieldParticipationResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new PlayerCustomFieldParticipationResolver();
    }

    public function test_only_validated_values_count_and_the_latest_validated_wins(): void
    {
        $event = $this->upcomingEvent();
        $player = User::factory()->create();
        $field = PlayerCustomField::factory()->create(['type' => PlayerCustomFieldType::Text]);

        PlayerCustomFieldValue::factory()->create([
            'player_user_id' => $player->id_user,
            'player_custom_field_id' => $field->id_player_custom_field,
            'value_text' => 'Rejected',
            'inserted_at' => time(),
            'review_status' => PlayerCustomFieldValueReviewStatus::Rejected,
            'reviewed_at' => time(),
        ]);
        PlayerCustomFieldValue::factory()->pending()->create([
            'player_user_id' => $player->id_user,
            'player_custom_field_id' => $field->id_player_custom_field,
            'value_text' => 'Pending',
            'inserted_at' => time(),
        ]);
        PlayerCustomFieldValue::factory()->create([
            'player_user_id' => $player->id_user,
            'player_custom_field_id' => $field->id_player_custom_field,
            'value_text' => 'Discarded',
            'inserted_at' => time(),
            'review_status' => PlayerCustomFieldValueReviewStatus::Discarded,
            'reviewed_at' => time(),
        ]);
        PlayerCustomFieldValue::factory()->create([
            'player_user_id' => $player->id_user,
            'player_custom_field_id' => $field->id_player_custom_field,
            'value_text' => 'Active',
            'inserted_at' => time() - 100,
            'reviewed_at' => time() - 100,
        ]);

        $result = $this->resolver->resolveForPlayers(
            (int) $event->id_event,
            [(int) $player->id_user],
            [(int) $field->id_player_custom_field],
            $this->cutoffFor($event)
        );

        $this->assertSame('Active', $result[(int) $player->id_user][0]['value']);
    }

    public function test_latest_inserted_at_wins(): void
    {
        $event = $this->upcomingEvent();
        $player = User::factory()->create();
        $field = PlayerCustomField::factory()->create(['type' => PlayerCustomFieldType::Text]);

        PlayerCustomFieldValue::factory()->create([
            'player_user_id' => $player->id_user,
            'player_custom_field_id' => $field->id_player_custom_field,
            'value_text' => 'Older',
            'inserted_at' => time() - 500,
        ]);
        PlayerCustomFieldValue::factory()->create([
            'player_user_id' => $player->id_user,
            'player_custom_field_id' => $field->id_player_custom_field,
            'value_text' => 'Newer',
            'inserted_at' => time(),
        ]);

        $result = $this->resolver->resolveForPlayers(
            (int) $event->id_event,
            [(int) $player->id_user],
            [(int) $field->id_player_custom_field],
            $this->cutoffFor($event)
        );

        $this->assertSame('Newer', $result[(int) $player->id_user][0]['value']);
    }

    public function test_multi_select_values_join_as_comma_separated_string(): void
    {
        $event = $this->upcomingEvent();
        $player = User::factory()->create();
        $field = PlayerCustomField::factory()->multiSelect()->create(['label' => 'Tags']);
        $optionA = PlayerCustomFieldOption::factory()->create([
            'player_custom_field_id' => $field->id_player_custom_field,
            'label' => 'Alpha',
        ]);
        $optionB = PlayerCustomFieldOption::factory()->create([
            'player_custom_field_id' => $field->id_player_custom_field,
            'label' => 'Beta',
        ]);

        $value = PlayerCustomFieldValue::factory()->create([
            'player_user_id' => $player->id_user,
            'player_custom_field_id' => $field->id_player_custom_field,
            'value_text' => null,
        ]);
        PlayerCustomFieldValueOption::query()->create([
            'player_custom_field_value_id' => $value->id_player_custom_field_value,
            'option_id' => $optionA->id_player_custom_field_option,
            'option_label_snapshot' => 'Alpha',
            'inserted_at' => time(),
        ]);
        PlayerCustomFieldValueOption::query()->create([
            'player_custom_field_value_id' => $value->id_player_custom_field_value,
            'option_id' => $optionB->id_player_custom_field_option,
            'option_label_snapshot' => 'Beta',
            'inserted_at' => time(),
        ]);

        $result = $this->resolver->resolveForPlayers(
            (int) $event->id_event,
            [(int) $player->id_user],
            [(int) $field->id_player_custom_field],
            $this->cutoffFor($event)
        );

        $this->assertSame('Alpha, Beta', $result[(int) $player->id_user][0]['value']);
    }

    public function test_returns_one_value_per_field(): void
    {
        $event = $this->upcomingEvent();
        $player = User::factory()->create();
        $fieldA = PlayerCustomField::factory()->create([
            'label' => 'Field A',
            'type' => PlayerCustomFieldType::Text,
            'sort_order' => 1,
        ]);
        $fieldB = PlayerCustomField::factory()->create([
            'label' => 'Field B',
            'type' => PlayerCustomFieldType::Text,
            'sort_order' => 2,
        ]);

        PlayerCustomFieldValue::factory()->create([
            'player_user_id' => $player->id_user,
            'player_custom_field_id' => $fieldA->id_player_custom_field,
            'value_text' => 'A value',
        ]);
        PlayerCustomFieldValue::factory()->create([
            'player_user_id' => $player->id_user,
            'player_custom_field_id' => $fieldA->id_player_custom_field,
            'value_text' => 'A duplicate',
            'inserted_at' => time() - 100,
        ]);
        PlayerCustomFieldValue::factory()->create([
            'player_user_id' => $player->id_user,
            'player_custom_field_id' => $fieldB->id_player_custom_field,
            'value_text' => 'B value',
        ]);

        $result = $this->resolver->resolveForPlayers(
            (int) $event->id_event,
            [(int) $player->id_user],
            [(int) $fieldA->id_player_custom_field, (int) $fieldB->id_player_custom_field],
            $this->cutoffFor($event)
        );

        $entries = $result[(int) $player->id_user];
        $this->assertCount(2, $entries);
        $this->assertSame('A value', $entries[0]['value']);
        $this->assertSame('B value', $entries[1]['value']);
    }

    public function test_no_requested_field_is_omitted_when_value_exists(): void
    {
        $event = $this->upcomingEvent();
        $player = User::factory()->create();
        $fields = collect(range(1, 3))->map(function (int $order): PlayerCustomField {
            $field = PlayerCustomField::factory()->create([
                'label' => 'Field ' . $order,
                'type' => PlayerCustomFieldType::Text,
                'sort_order' => $order,
            ]);
            PlayerCustomFieldValue::factory()->create([
                'player_user_id' => User::factory()->create()->id_user,
                'player_custom_field_id' => $field->id_player_custom_field,
                'value_text' => 'Other player',
            ]);

            return $field;
        });

        foreach ($fields as $field) {
            PlayerCustomFieldValue::factory()->create([
                'player_user_id' => $player->id_user,
                'player_custom_field_id' => $field->id_player_custom_field,
                'value_text' => 'Value for ' . $field->label,
            ]);
        }

        $fieldIds = $fields->pluck('id_player_custom_field')->map(static fn ($id): int => (int) $id)->all();
        $result = $this->resolver->resolveForPlayers(
            (int) $event->id_event,
            [(int) $player->id_user],
            $fieldIds,
            $this->cutoffFor($event)
        );

        $this->assertCount(3, $result[(int) $player->id_user]);
        $this->assertSame(
            ['Field 1', 'Field 2', 'Field 3'],
            array_column($result[(int) $player->id_user], 'label')
        );
    }

    public function test_recent_answers_cap_at_two_newest_by_inserted_at(): void
    {
        $event = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-06-15'));
        $player = User::factory()->create();
        $field = PlayerCustomField::factory()->create([
            'label' => 'Shirt Size',
            'type' => PlayerCustomFieldType::Text,
        ]);

        foreach ([['S', 1000], ['M', 2000], ['L', 3000]] as [$size, $insertedAt]) {
            PlayerCustomFieldValue::factory()->create([
                'player_user_id' => $player->id_user,
                'player_custom_field_id' => $field->id_player_custom_field,
                'value_text' => $size,
                'inserted_at' => $insertedAt,
            ]);
        }

        $result = $this->resolver->resolveForPlayers(
            (int) $event->id_event,
            [(int) $player->id_user],
            [(int) $field->id_player_custom_field],
            $this->cutoffFor($event)
        );

        $recent = $result[(int) $player->id_user][0]['recent_answers'];
        $this->assertCount(2, $recent);
        $this->assertSame('M', $recent[0]['value']);
        $this->assertSame('L', $recent[1]['value']);
    }

    public function test_answers_submitted_after_the_cutoff_are_excluded(): void
    {
        $event = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-06-15'));
        $player = User::factory()->create();
        $field = PlayerCustomField::factory()->create(['type' => PlayerCustomFieldType::Text]);

        PlayerCustomFieldValue::factory()->create([
            'player_user_id' => $player->id_user,
            'player_custom_field_id' => $field->id_player_custom_field,
            'value_text' => 'Now',
            'inserted_at' => Carbon::parse('2026-06-14', config('app.timezone'))->timestamp,
        ]);
        PlayerCustomFieldValue::factory()->create([
            'player_user_id' => $player->id_user,
            'player_custom_field_id' => $field->id_player_custom_field,
            'value_text' => 'Too late',
            'inserted_at' => Carbon::parse('2026-06-16', config('app.timezone'))->timestamp,
        ]);

        $result = $this->resolver->resolveForPlayers(
            (int) $event->id_event,
            [(int) $player->id_user],
            [(int) $field->id_player_custom_field],
            $this->cutoffFor($event)
        );

        $recent = $result[(int) $player->id_user][0]['recent_answers'];
        $this->assertCount(1, $recent);
        $this->assertSame('Now', $recent[0]['value']);
    }

    public function test_recent_answers_report_the_submission_channel_and_date(): void
    {
        $event = PadelEvent::factory()->create(UnixEventFactory::eventBounds('2026-06-15'));
        $player = User::factory()->create();
        $field = PlayerCustomField::factory()->create(['type' => PlayerCustomFieldType::Text]);

        PlayerCustomFieldValue::factory()->create([
            'player_user_id' => $player->id_user,
            'player_custom_field_id' => $field->id_player_custom_field,
            'value_text' => 'Answer',
            'submitted_via' => PlayerCustomFieldValue::SUBMITTED_VIA_CSV,
            'inserted_at' => Carbon::parse('2026-01-10', config('app.timezone'))->timestamp,
        ]);

        $result = $this->resolver->resolveForPlayers(
            (int) $event->id_event,
            [(int) $player->id_user],
            [(int) $field->id_player_custom_field],
            $this->cutoffFor($event)
        );

        $recent = $result[(int) $player->id_user][0]['recent_answers'];
        $this->assertCount(1, $recent);
        $this->assertSame('Answer', $recent[0]['value']);
        $this->assertSame(PlayerCustomFieldValue::SUBMITTED_VIA_CSV, $recent[0]['submitted_via']);
        $this->assertSame('2026-01-10', $recent[0]['inserted_date']);
    }

    /**
     * The factory date is random, so pin it ahead of the answers under test.
     */
    private function upcomingEvent(): PadelEvent
    {
        return PadelEvent::factory()->create([
            ...UnixEventFactory::eventBounds(Carbon::now(config('app.timezone'))->addMonth()->format('Y-m-d')),
        ]);
    }

    private function cutoffFor(PadelEvent $event): string
    {
        return \App\Services\EventCalendarTiming::dayYmd((int) $event->event_starts_at);
    }
}
