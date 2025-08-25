<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Padeliz API Documentation</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 2rem;
        }
        .version-list {
            list-style: none;
            padding: 0;
        }
        .version-item {
            margin-bottom: 1rem;
        }
        .version-link {
            display: block;
            padding: 1rem;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            text-align: center;
            transition: background-color 0.2s;
        }
        .version-link:hover {
            background: #0056b3;
            color: white;
            text-decoration: none;
        }
        .version-number {
            font-size: 1.2rem;
            font-weight: bold;
        }
        .version-description {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Padeliz API Documentation</h1>

        <p style="text-align: center; color: #666; margin-bottom: 2rem;">
            Select an API version to view its documentation:
        </p>

        <ul class="version-list">
            @foreach($apiVersions as $version)
                <li class="version-item">
                    <a href="/api/documentation/{{ $version }}" class="version-link">
                        <div class="version-number">API {{ strtoupper($version) }}</div>
                        <div class="version-description">View {{ strtoupper($version) }} documentation</div>
                    </a>
                </li>
            @endforeach
        </ul>

        @if(empty($apiVersions))
            <p style="text-align: center; color: #666;">
                No API versions found.
            </p>
        @endif
    </div>
</body>
</html>
