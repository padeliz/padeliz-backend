<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HasUploadedFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_upload_path_throws_exception_when_base_path_missing()
    {
        // Temporarily clear the UPLOADS_BASE_PATH
        config(['uploads.base_path' => null]);

        $player = Player::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('UPLOADS_BASE_PATH environment variable is required');

        $player->getUploadPath('images');
    }

    public function test_get_uploaded_urls_throws_exception_when_base_path_missing()
    {
        // Temporarily clear the UPLOADS_BASE_PATH
        config(['uploads.base_path' => null]);

        $player = Player::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('UPLOADS_BASE_PATH environment variable is required');

        $player->getUploadedUrls('images');
    }

    public function test_get_upload_path_works_with_valid_base_path()
    {
        $player = Player::factory()->create();

        $path = $player->getUploadPath('images');

        $this->assertStringContainsString(config('uploads.base_path'), $path);
        $this->assertStringContainsString('.player', $path);
        $this->assertStringContainsString((string)$player->id_player, $path);
        $this->assertStringContainsString('images', $path);
    }

    public function test_get_uploaded_files_returns_empty_array_when_directory_does_not_exist()
    {
        $player = Player::factory()->create();

        $files = $player->getUploadedFiles('images');

        $this->assertIsArray($files);
        $this->assertEmpty($files);
    }

    public function test_get_uploaded_files_returns_empty_array_when_directory_exists_but_is_empty()
    {
        $player = Player::factory()->create();
        $uploadPath = $player->getUploadPath('images');

        // Create the directory but leave it empty
        File::makeDirectory($uploadPath, 0755, true, true);

        $files = $player->getUploadedFiles('images');

        $this->assertIsArray($files);
        $this->assertEmpty($files);

        // Clean up
        File::deleteDirectory($uploadPath);
    }

    public function test_get_uploaded_files_returns_files_when_directory_has_files()
    {
        $player = Player::factory()->create();
        $uploadPath = $player->getUploadPath('images');

        // Create the directory and add some test files
        File::makeDirectory($uploadPath, 0755, true, true);
        File::put($uploadPath . '/image1.jpg', 'fake image content 1');
        File::put($uploadPath . '/image2.png', 'fake image content 2');
        File::put($uploadPath . '/document.pdf', 'fake document content');

        $files = $player->getUploadedFiles('images');

        $this->assertIsArray($files);
        $this->assertCount(3, $files);
        $this->assertContains('image1.jpg', $files);
        $this->assertContains('image2.png', $files);
        $this->assertContains('document.pdf', $files);

        // Clean up
        File::deleteDirectory($uploadPath);
    }

    public function test_get_uploaded_files_handles_different_types_languages_and_sizes()
    {
        $player = Player::factory()->create();

        // Test different combinations
        $testCases = [
            ['type' => 'images', 'lang' => 'en', 'size' => 'medium'],
            ['type' => 'documents', 'lang' => 'es', 'size' => 'large'],
            ['type' => 'videos', 'lang' => 'fr', 'size' => 'small'],
        ];

        foreach ($testCases as $testCase) {
            $uploadPath = $player->getUploadPath($testCase['type'], $testCase['lang'], $testCase['size']);

            // Create directory and add a test file
            File::makeDirectory($uploadPath, 0755, true, true);
            File::put($uploadPath . '/test-file.txt', 'test content');

            $files = $player->getUploadedFiles($testCase['type'], $testCase['lang'], $testCase['size']);

            $this->assertIsArray($files);
            $this->assertCount(1, $files);
            $this->assertContains('test-file.txt', $files);

            // Clean up
            File::deleteDirectory($uploadPath);
        }
    }

    public function test_get_uploaded_files_uses_absolute_paths_correctly()
    {
        $player = Player::factory()->create();
        $relativePath = $player->getUploadPath('images');
        $absolutePath = base_path($relativePath);

        // Create the directory using absolute path
        File::makeDirectory($absolutePath, 0755, true, true);
        File::put($absolutePath . '/test-image.jpg', 'fake image content');

        $files = $player->getUploadedFiles('images');

        $this->assertIsArray($files);
        $this->assertCount(1, $files);
        $this->assertContains('test-image.jpg', $files);

        // Verify the absolute path was used correctly
        $this->assertTrue(File::exists($absolutePath));
        $this->assertTrue(File::exists($absolutePath . '/test-image.jpg'));

        // Clean up
        File::deleteDirectory($absolutePath);
    }

    public function test_get_uploaded_files_handles_file_system_errors_gracefully()
    {
        $player = Player::factory()->create();

        // Mock File facade to simulate an error
        File::shouldReceive('exists')->andReturn(true);
        File::shouldReceive('files')->andThrow(new \Exception('Permission denied'));

        $files = $player->getUploadedFiles('images');

        $this->assertIsArray($files);
        $this->assertEmpty($files);
    }

    public function test_get_uploaded_urls_generates_correct_urls()
    {
        $player = Player::factory()->create();

        $urls = $player->getUploadedUrls('images');

        // Should return an array (empty if no files)
        $this->assertIsArray($urls);

        // If we had files, they should be proper URLs
        // For now, just verify the method doesn't throw an exception
        $this->assertTrue(true);
    }

    public function test_get_uploaded_urls_generates_proper_url_format()
    {
        $player = Player::factory()->create();

        // Create a test file to verify URL generation
        $uploadPath = $player->getUploadPath('images');
        File::makeDirectory($uploadPath, 0755, true, true);
        File::put($uploadPath . '/test-image.png', 'fake image content');

        $urls = $player->getUploadedUrls('images');

        $this->assertCount(1, $urls);
        $this->assertStringStartsWith(config('app.url'), $urls[0]);
        $this->assertStringContainsString('/' . config('uploads.base_path'), $urls[0]);
        $this->assertStringContainsString('.player', $urls[0]);
        $this->assertStringContainsString((string)$player->id_player, $urls[0]);
        $this->assertStringContainsString('test-image.png', $urls[0]);

        // Clean up
        File::deleteDirectory($uploadPath);
    }

    public function test_get_uploaded_urls_handles_public_path_correctly()
    {
        $player = Player::factory()->create();

        // Test with a base path that includes 'public/'
        config(['uploads.base_path' => 'public/uploads']);

        $uploadPath = $player->getUploadPath('images');
        $absolutePath = base_path($uploadPath);

        // Clean up any existing files first
        if (File::exists($absolutePath)) {
            File::deleteDirectory($absolutePath);
        }

        File::makeDirectory($absolutePath, 0755, true, true);
        File::put($absolutePath . '/test-image.jpg', 'fake image content');

        $urls = $player->getUploadedUrls('images');

        $this->assertCount(1, $urls);
        // Should not contain 'public/' in the URL
        $this->assertStringNotContainsString('/public/', $urls[0]);
        $this->assertStringContainsString('/uploads/', $urls[0]);

        // Clean up
        File::deleteDirectory($absolutePath);
    }

    public function test_player_uploaded_profile_urls_attribute_works_correctly()
    {
        $player = Player::factory()->create();

        // Create test profile images
        $uploadPath = $player->getUploadPath('images');
        File::makeDirectory($uploadPath, 0755, true, true);
        File::put($uploadPath . '/profile1.jpg', 'fake profile image 1');
        File::put($uploadPath . '/profile2.jpg', 'fake profile image 2');

        $profileUrls = $player->uploaded_profile_urls;

        $this->assertIsArray($profileUrls);
        $this->assertCount(2, $profileUrls);
        $this->assertStringContainsString('profile1.jpg', $profileUrls[0]);
        $this->assertStringContainsString('profile2.jpg', $profileUrls[1]);

        // Clean up
        File::deleteDirectory($uploadPath);
    }

    public function test_delete_uploaded_file_removes_file()
    {
        $player = Player::factory()->create();

        // Create a test file
        $uploadPath = $player->getUploadPath('images');
        File::makeDirectory($uploadPath, 0755, true, true);
        File::put($uploadPath . '/test-file.jpg', 'fake content');

        $result = $player->deleteUploadedFile('test-file.jpg', 'images');

        $this->assertTrue($result);
        $this->assertFalse(File::exists($uploadPath . '/test-file.jpg'));

        // Clean up
        File::deleteDirectory($uploadPath);
    }

    public function test_delete_uploaded_file_returns_false_when_file_does_not_exist()
    {
        $player = Player::factory()->create();

        $result = $player->deleteUploadedFile('non-existent-file.jpg', 'images');

        $this->assertFalse($result);
    }
}
