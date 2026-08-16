<img src="https://github.com/user-attachments/assets/6ea788c4-f033-45b8-8701-bae663d57119" width="400">

### v1: Padeliz API

Service Laravel-based RESTful endpoints for:
- padeliz-frontend-app
- padeliz-frontend-manager
- padeliz-frontend-admin
___


### 🛠️ Development Setup (Windows)

This project uses VS Code DevContainers for development with optimized performance for Google API client services.

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling.
___


### 📋 Prerequisites
- [Git](https://git-scm.com/downloads) pack
- [Docker Desktop for Windows](https://www.docker.com/products/docker-desktop/)
  - WSL2 enabled (Windows Subsystem for Linux)
- [VS Code](https://code.visualstudio.com/) with the "Dev Containers" extension
___


### ⚡ Performance Notes
- **WSL2 Users**: The devcontainer is optimized for WSL2 performance issues
- **Google API Client**: Handles large package installations without timeout issues
- **Volume Mounting**: Uses optimized mounting strategy for better file I/O performance
___



### 🚀 First-time setup

1. **Install VS Code + “Dev Containers” extension**

2. **Clone the repository**
   ```bash
   ## in the VSCode terminal
   git clone https://github.com/padeliz/padeliz-backend.git
   ```

   ```bash
   # Copy the environment file
   cd padeliz-backend && cp .env.example .env
   ```

3. **Open the repository folder in VS Code**

4. **Open the Command Palette and run "Dev Containers: Reopen in Container"**

    Press CTRL + P and type `> Dev Containers: Reopen in Container`
    - This will open a new window in which the container is initialized.
    - You're now editing the files directly in the Docker.

5. **Install Dependencies**

    *No need to do it manually.*

    *Wait for the container to build and proceed with:*

    - *installing all dependencies*
    - *generating application key*
    - *running database migrations*
    - *setting up the development environment*

6. **Expose uploaded files (user uploads)**

   User-uploaded files (profile photos, documents, etc.) use the disk named by `USER_UPLOADS_DISK` (default `public`, i.e. `storage/app/public`). Create the web symlink once per environment:

   ```bash
   php artisan storage:link
   ```

   For S3 later, set `USER_UPLOADS_DISK=s3` in `.env` and configure the `s3` disk; database rows keep paths relative to that disk (profile photos, eligibility documents, etc.).
___


### 🔗 Available Endpoints

The API is available at `http://localhost:8000`

Its Swagger docs is available at `http://localhost:8000/api/documentation`

Manage DB data from phpMyAdmin: `http://localhost:8080`
___


### 💻 Useful Commands

Once inside the DevContainer, you can use these commands:

```bash
# Commonly useful

php artisan migrate # Run migrations

php artisan test # Run tests

composer format # Format all code
composer format:check # Check formatting without making changes
composer format:fix # Fix formatting with verbose output

php artisan cache:clear
php artisan config:clear
```

```bash
# Rarely useful

composer install # Install dependencies

# ⚠️ WARNING: Never run seeders in production! They will erase all existing data.
php artisan db:seed # Repopulate database with dummy data

mysql -u [USER] -p [PASS] # Access MySQL CLI

php artisan route:clear
php artisan view:clear
```
___


### 📁 Project Structure

  ```bash
  ├── .devcontainer/     # DevContainer configuration
  ├── app/               # Application core code
  ├── database/          # Migrations and seeders
  ├── routes/            # API routes
  ├── tests/             # Test files
  └── docker/            # Docker configuration files
```
___


### 🗄️ Database Access

You can connect to the database using these credentials:
- Host: localhost
- Port: 3306
- Database: db_padeliz
- Username: db_user
- Password: db_pass
___


### 🤝 Contributing

1. Create a new branch for your feature
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. Make your changes and test them
   ```bash
   php artisan test
   ```

3. Stage changes
   ```bash
   git add .

   # Wait for the staged code formatting...
   composer format # Or do it manually for the entire code
   ```

3. Commit
   ```bash
   git commit -m "Description of changes"
   ```

3. Push to your branch
   ```bash
   git push origin feature/your-feature-name
   ```

4. Create a Pull Request on GitHub
___


### 📚 Generating Documentation

The API documentation: `http://localhost:8000/api/documentation`

Regenerate the documentation after:
- making changes to the API endpoints or models
- creating a new version
    - *by adding a new version file (e.g., `routes/api/v3.php`)*


```bash
# to generate documentation for all API versions:
php artisan api:docs:generate-all

# to generate documentation for a specific version:
php artisan l5-swagger:generate v1
```
___


### 🔧 Troubleshooting

1. **Port Conflicts**
   - If port 8000 or 3306 is already in use, modify it overall in the project.

2. **Docker Issues**
   - Ensure Docker Desktop is running
   - Try restarting Docker Desktop
   - Check WSL2 is properly installed: `wsl --status`
     - For optimal performance: ``cp .wslconfig.example C:\Users\[YourUsername]\.wslconfig``

3. **Permission Issues**
   - Run Docker Desktop as administrator
   - Ensure proper file permissions in WSL

4. **No Access to Documentation**

    1. Make sure storage directory is writable:
        ```bash
        chmod -R 777 storage/
        ```

    2. Clear Laravel cache:
        ```bash
        php artisan cache:clear
        ```

    3. Verify L5-Swagger is properly installed:
        ```bash
        php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
        ```
___


### ✨ Automatic Code Formatting

This project is configured with automatic code formatting.

What's Configured:
- **VS Code Settings**: Manual formatting only
- **Git Pre-commit Hook**: Automatic formatting before commits (shared via scripts/git-hooks/)
- **PHP CS Fixer**: PSR-12 compliant code formatting with Laravel-specific rules
- **EditorConfig**: Consistent editor behavior across different IDEs
- **DevContainer Integration**: All extensions and settings are automatically installed
- **Cursor AI Compatible**: No automatic formatting during editing to prevent conflicts

How It Works:
1. **Pre-commit Hook**: Code is automatically formatted before Git commits
2. **Manual Formatting**: Use `Ctrl+Shift+P` → "Format Document" or `Shift+Alt+F`
3. **VS Code Tasks**: Use `Ctrl+Shift+P` → "Tasks: Run Task" → Choose formatting task
4. **Composer Commands**: Use `composer format` for bulk formatting
___


### 📦 File storage convention

Public user/media files live on the disk named by `USER_UPLOADS_DISK` (default `public` → `storage/app/public`). Relative DB columns use `{subject}_path` and the path shape:

```text
{table-kebab}/{rowId}/{subject}/{filename}
```

Run `php artisan storage:link` once per environment so `/storage` URLs work for the `public` disk (user uploads).
___


### 🧩 Schedule templates (`resources/template-matches`)

Committed schedule templates are **not** under `storage/app`. They live in:

```text
resources/template-matches/v{N}/players-{P}-partners-{O}-repeat-{R}-courts-{C}[-fixedteams].json
```

- Runtime schedule generation (Mode A and Mode B) always loads **mixed** templates (never `-fixedteams`).
- `-fixedteams` JSON is kept for metrics / demo inspection and can be (re)generated offline via CLI.
- Highest compatible `v{N}/` is used unless a request/command passes an explicit version.

**Artisan writer cycle (offline; long runs — generate/regenerate bump PHP memory to 512M):**

```bash
php artisan templates:versions
php artisan templates:generate --templates-version=5 --players=4 --partners=1
php artisan templates:regenerate --templates-version=5
php artisan templates:recalculate-quality-metrics --templates-version=4
```

**Artisan metrics (mixed by default):**

```bash
php artisan templates:metrics --templates-version=4
php artisan templates:metrics --templates-version=4 --fixed-teams=1
php artisan templates:metrics --templates-version=4 --include-fixed-teams
php artisan templates:metrics --templates-version=4 --fixed-teams=1 --combinations="8:2,3"
```

Verify tests do not call real DFS (`->generate(`) except `RecordingGenerator.php`:

```bash
composer test:lint
```
___


### 📄 License

This project is private. All rights reserved.
