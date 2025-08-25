<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ApiDocumentationController extends Controller
{
    public function index()
    {
        // Get available API versions from routes directory
        $apiVersions = [];
        $routesPath = base_path('routes/api');

        if (File::exists($routesPath)) {
            $files = File::files($routesPath);
            foreach ($files as $file) {
                if (preg_match('/^v(\d+)\.php$/', $file->getFilename(), $matches)) {
                    $apiVersions[] = 'v' . $matches[1];
                }
            }
        }

        // Sort versions numerically
        usort($apiVersions, function ($a, $b) {
            $aNum = (int) substr($a, 1);
            $bNum = (int) substr($b, 1);
            return $aNum - $bNum;
        });

        return view('api-documentation-index', compact('apiVersions'));
    }
}
