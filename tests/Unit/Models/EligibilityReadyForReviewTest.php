<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Eligibility;
use Tests\TestCase;

class EligibilityReadyForReviewTest extends TestCase
{
    public function test_ready_for_review_false_when_missing_required_fields(): void
    {
        $e = new Eligibility();
        $e->media_source = null;
        $e->friends_source = null;
        $e->first_name = 'A';
        $e->last_name = 'B';
        $e->phone = '123';
        $e->birth_year = 2000;
        $e->submitted_at = time();

        $e->recomputeReadyForReview();

        $this->assertFalse($e->ready_for_review);
    }

    public function test_ready_for_review_true_when_allowlist_satisfied(): void
    {
        $e = new Eligibility();
        $e->media_source = [1];
        $e->friends_source = null;
        $e->first_name = 'A';
        $e->last_name = 'B';
        $e->phone = '123';
        $e->birth_year = 2000;
        $e->submitted_at = time();

        $e->recomputeReadyForReview();

        $this->assertTrue($e->ready_for_review);
    }

    public function test_ready_for_review_true_when_friends_source_used_instead_of_media_source(): void
    {
        $e = new Eligibility();
        $e->media_source = null;
        $e->friends_source = ['Alice'];
        $e->first_name = 'A';
        $e->last_name = 'B';
        $e->phone = '123';
        $e->birth_year = 2000;
        $e->submitted_at = time();

        $e->recomputeReadyForReview();

        $this->assertTrue($e->ready_for_review);
    }

    public function test_ready_for_review_true_when_submitted_at_missing_but_resubmitted_at_present(): void
    {
        $e = new Eligibility();
        $e->media_source = [1];
        $e->friends_source = null;
        $e->first_name = 'A';
        $e->last_name = 'B';
        $e->phone = '123';
        $e->birth_year = 2000;
        $e->submitted_at = null;
        $e->resubmitted_at = time();

        $e->recomputeReadyForReview();

        $this->assertTrue($e->ready_for_review);
    }

    public function test_ready_for_review_false_when_phone_missing(): void
    {
        $e = new Eligibility();
        $e->media_source = [1];
        $e->friends_source = null;
        $e->first_name = 'A';
        $e->last_name = 'B';
        $e->phone = null;
        $e->birth_year = 2000;
        $e->submitted_at = time();

        $e->recomputeReadyForReview();

        $this->assertFalse($e->ready_for_review);
    }
}
