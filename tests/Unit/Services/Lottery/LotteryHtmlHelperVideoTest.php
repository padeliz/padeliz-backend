<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Lottery;

use App\Services\Lottery\LotteryHtmlHelper;
use PHPUnit\Framework\TestCase;

class LotteryHtmlHelperVideoTest extends TestCase
{
    public function test_is_prize_video_detects_mp4_webm_mov(): void
    {
        $this->assertTrue(LotteryHtmlHelper::isPrizeVideo('prize.mp4'));
        $this->assertTrue(LotteryHtmlHelper::isPrizeVideo('/media/a.webm'));
        $this->assertTrue(LotteryHtmlHelper::isPrizeVideo('clip.MOV'));
        $this->assertFalse(LotteryHtmlHelper::isPrizeVideo('prize.png'));
        $this->assertFalse(LotteryHtmlHelper::isPrizeVideo(null));
        $this->assertFalse(LotteryHtmlHelper::isPrizeVideo(''));
    }
}
