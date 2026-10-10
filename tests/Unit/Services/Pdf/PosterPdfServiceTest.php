<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Pdf;

use App\Models\Organizer;
use App\Models\Player;
use App\Services\Pdf\PdfHtmlHelper;
use App\Services\Pdf\PosterPdfService;
use App\Services\Schedule\PointsPerMatchCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use ReflectionMethod;
use Tests\TestCase;

final class PosterPdfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_scratch_edition_label_empty_is_not_shown_and_not_scratch(): void
    {
        $presentation = $this->invokeScratchEditionLabelPresentation([
            'edition_label' => '',
            'organizer_id' => null,
        ]);

        $this->assertSame('', $presentation['edition_label']);
        $this->assertFalse($presentation['show_event_label']);
        $this->assertSame('#000000', $presentation['edition_label_muted']);
        $this->assertSame('#000000', $presentation['edition_label_accent']);
        $this->assertStringNotContainsString('Scratch', $presentation['edition_label']);
    }

    public function test_scratch_edition_label_whitespace_only_is_hidden(): void
    {
        $presentation = $this->invokeScratchEditionLabelPresentation([
            'edition_label' => "  \t",
            'organizer_id' => null,
        ]);

        $this->assertFalse($presentation['show_event_label']);
    }

    public function test_scratch_edition_label_black_without_organizer(): void
    {
        $presentation = $this->invokeScratchEditionLabelPresentation([
            'edition_label' => 'Mini 12',
            'organizer_id' => null,
        ]);

        $this->assertTrue($presentation['show_event_label']);
        $this->assertSame('#000000', $presentation['edition_label_muted']);
        $this->assertSame('#000000', $presentation['edition_label_accent']);

        $html = View::make('pdf.ranking', $this->minimalRankingViewData([
            'edition_label' => 'Mini 12',
            'show_event_label' => true,
            'edition_label_muted' => '#000000',
            'edition_label_accent' => '#000000',
            'show_player_ids' => false,
        ]))->render();

        $this->assertStringContainsString('Mini 12', $html);
        $this->assertStringContainsString('color: #000000', $html);
    }

    public function test_scratch_edition_label_green_with_organizer_even_if_organizer_flag_false(): void
    {
        $organizer = Organizer::factory()->create(['show_event_label' => false]);

        $presentation = $this->invokeScratchEditionLabelPresentation([
            'edition_label' => 'Shown Anyway',
            'organizer_id' => $organizer->id_organizer,
        ]);

        $this->assertTrue($presentation['show_event_label']);
        $this->assertSame('#a9d78b', $presentation['edition_label_muted']);
        $this->assertSame('#7ab857', $presentation['edition_label_accent']);
    }

    public function test_ranking_blade_hides_player_ids_when_flag_false(): void
    {
        $html = View::make('pdf.ranking', $this->minimalRankingViewData([
            'show_player_ids' => false,
            'players' => [[
                'id' => 866,
                'name' => 'Zaza',
                'short_name' => 'Zaza',
                'html_short_name' => 'Zaza',
                'name_font_size' => 24,
                'avatar_path' => public_path('media/prints/MiniTour-best-teams.png'),
                'is_collecting_points' => true,
            ]],
        ]))->render();

        $this->assertStringNotContainsString('#866', $html);
    }

    public function test_ranking_blade_shows_player_ids_by_default(): void
    {
        $html = View::make('pdf.ranking', $this->minimalRankingViewData([
            'players' => [[
                'id' => 866,
                'name' => 'Zaza',
                'short_name' => 'Zaza',
                'html_short_name' => 'Zaza',
                'name_font_size' => 24,
                'avatar_path' => public_path('media/prints/MiniTour-best-teams.png'),
                'is_collecting_points' => true,
            ]],
        ]))->render();

        $this->assertStringContainsString('#866', $html);
    }

    public function test_edition_zone_ranking_view_defaults_keep_green_edition_label_colors(): void
    {
        $service = new PosterPdfService();
        $method = new ReflectionMethod($service, 'buildRankingViewData');
        $method->setAccessible(true);

        $payload = $method->invoke(
            $service,
            'Div',
            '#7ab857',
            'Ed',
            null,
            null,
            true,
            'Court',
            10,
            true,
            true,
            true,
            [],
            5,
            false,
        );

        $this->assertSame('#a9d78b', $payload['edition_label_muted']);
        $this->assertSame('#7ab857', $payload['edition_label_accent']);
        $this->assertTrue($payload['show_player_ids']);
    }

    public function test_scratch_preview_applies_adjust_and_print_for_pdfs(): void
    {
        $players = Player::factory()->count(8)->create();
        $playerIds = $players->pluck('id_player')->all();
        $baseInput = [
            'player_ids' => $playerIds,
            'court_names' => ['Court 1'],
            'time_start' => '14:00',
            'time_end' => '17:00',
            'opponents_per_player' => 4,
            'repeat_partners' => 1,
            'include_final' => false,
            'has_demonstrative_match' => false,
        ];

        $base = $this->invokeScratchPreview($baseInput);
        $adjusted = $this->invokeScratchPreview($baseInput + ['adjust' => 2]);

        $this->assertSame(
            (int) $base['points_per_match'] + 2,
            (int) $adjusted['points_per_match']
        );
        $this->assertSame(2, (int) $adjusted['adjust']);
        $expectedPrint = PointsPerMatchCalculator::evenRoundForPrint(
            (int) $adjusted['points_per_match']
        );
        $this->assertSame($expectedPrint, (int) $adjusted['points_per_match_print']);

        $rankingHtml = View::make('pdf.ranking', $this->minimalRankingViewData([
            'include_scores' => true,
            'points_per_match_print' => $expectedPrint,
        ]))->render();
        $this->assertStringContainsString(
            $expectedPrint . ' points per match',
            $rankingHtml
        );

        $matchesHtml = View::make('pdf.matches', $this->minimalMatchesViewData([
            'include_scores' => true,
            'points_per_match_print' => $expectedPrint,
        ]))->render();
        $this->assertStringContainsString(
            $expectedPrint . ' points per match',
            $matchesHtml
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function invokeScratchPreview(array $input): array
    {
        $service = new PosterPdfService();
        $method = new ReflectionMethod($service, 'scratchPreview');
        $method->setAccessible(true);

        return $method->invoke($service, $input);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function invokeScratchEditionLabelPresentation(array $input): array
    {
        $service = new PosterPdfService();
        $method = new ReflectionMethod($service, 'scratchEditionLabelPresentation');
        $method->setAccessible(true);

        return $method->invoke($service, $input);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function minimalRankingViewData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Test Division',
            'color' => '#7ab857',
            'edition_label' => '',
            'organizer_image' => null,
            'club_host_image' => null,
            'show_event_label' => false,
            'edition_label_muted' => '#a9d78b',
            'edition_label_accent' => '#7ab857',
            'show_player_ids' => true,
            'court_label' => 'Court 1',
            'points_per_match_print' => 10,
            'include_scores' => false,
            'include_final' => false,
            'allow_replacements' => true,
            'players' => [],
            'player_matches_count' => 1,
            'matches_rows' => [1],
            'nr_of_rows' => 1,
            'margin_top' => 8,
            'sizing' => PdfHtmlHelper::PLAYERS_ROWS_SIZING['1'],
            'show_column_headers' => false,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function minimalMatchesViewData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Test Division',
            'color' => '#7ab857',
            'edition_label' => '',
            'organizer_image' => null,
            'club_host_image' => null,
            'show_event_label' => false,
            'edition_label_muted' => '#a9d78b',
            'edition_label_accent' => '#7ab857',
            'court_name' => 'Court 1',
            'points_per_match_print' => 10,
            'include_scores' => false,
            'include_final' => false,
            'has_demonstrative_match' => false,
            'allow_replacements' => true,
            'final_points_target' => 24,
            'time_end' => '17:00',
            'matches' => [],
            'margin_top' => 8,
            'match_break_column' => -1,
            'matches_without_margin_top' => [0],
            'stretching_image' => public_path('media/prints/MiniTour-step-stretching.jpg'),
            'finalist_image' => public_path('media/prints/MiniTour-finalist-team.jpg'),
            'best_teams_image' => public_path('media/prints/MiniTour-best-teams.png'),
            'special_guest_image' => public_path('media/prints/special-guests/1.png'),
            'demo_time' => '',
            'stretch_time' => '',
            'final_time' => '',
        ], $overrides);
    }
}
