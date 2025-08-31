<img src="https://github.com/user-attachments/assets/6ea788c4-f033-45b8-8701-bae663d57119" width="400">

## Padeliz API

Service Laravel-based endpoints for:
- padeliz-frontend-app
- padeliz-frontend-manager
- padeliz-frontend-admin


## Development Setup (Windows)

This project uses VS Code DevContainers for development with optimized performance for Google API client services.

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling.


### Prerequisites
- [Git](https://git-scm.com/downloads)
- [Docker Desktop for Windows](https://www.docker.com/products/docker-desktop/)
- WSL2 enabled (Windows Subsystem for Linux)
    - *copy `.wslconfig.example` to your Windows user directory for optimal performance*
- [VS Code](https://code.visualstudio.com/) with the "Dev Containers" extension


### Performance Notes
- **WSL2 Users**: The devcontainer is optimized for WSL2 performance issues
- **Google API Client**: Handles large package installations without timeout issues
- **Volume Mounting**: Uses optimized mounting strategy for better file I/O performance


### First-time setup

1. **Install VS Code + “Dev Containers” extension**

2. **Clone the repository**
   ```bash
   ## in the VSCode terminal
   git clone https://github.com/padeliz/padeliz-backend.git
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

6. **Environment Setup**
   ```bash
   # Copy the environment file (if not exists)
   cp .env.example .env
   ```


### Available Endpoints

The API is available at `http://localhost:8000`

Its Swagger docs is available at `http://localhost:8000/api/documentation`

Manage DB data from phpMyAdmin: `http://localhost:8080`


### Useful Commands

Once inside the DevContainer, you can use these commands:

```bash
# Commonly useful

php artisan migrate # Run migrations

vendor/bin/phpunit # Run tests

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


### Project Structure

  ```bash
  ├── app/               # Application core code
  ├── database/          # Migrations and seeders
  ├── .devcontainer/     # DevContainer configuration
  ├── routes/            # API routes
  ├── tests/             # Test files
  └── docker/            # Docker configuration files
```


### Database Access

You can connect to the database using these credentials:
- Host: localhost
- Port: 3306
- Database: db_padeliz
- Username: db_user
- Password: db_pass


### Troubleshooting

1. **Port Conflicts**
   - If port 8000 or 3306 is already in use, modify the ports in `docker-compose.yml`

2. **Docker Issues**
   - Ensure Docker Desktop is running
   - Try restarting Docker Desktop
   - Check WSL2 is properly installed: `wsl --status`

3. **Permission Issues**
   - Run Docker Desktop as administrator
   - Ensure proper file permissions in WSL


### Contributing

1. Create a new branch for your feature
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. Make your changes and test them
   ```bash
   php artisan test
   ```

3. Commit
   ```bash
   git add .
   git commit -m "Description of changes"
   ```

3. Push to your branch
   ```bash
   git push origin feature/your-feature-name
   ```

4. Create a Pull Request on GitHub


### Generating Documentation

The API documentation is automatically generated for each version and is available at: `http://localhost:8000/api/documentation`

```bash
# to generate documentation for all API versions:
php artisan api:docs:generate-all

# to generate documentation for a specific version:
php artisan l5-swagger:generate v1
```

When you add a new version file (e.g., `routes/api/v3.php`), the documentation will be automatically detected and registered. Simply run the generation command to create the documentation for the new version.

If you make changes to the API endpoints or models, regenerate the documentation.


### Automatic Code Formatting

This project is configured with automatic code formatting that works out of the box. No manual setup required!

#### What's Configured:
- **VS Code Settings**: Automatic formatting on save, proper indentation (4 spaces for PHP, 2 for JSON/YAML)
- **PHP CS Fixer**: PSR-12 compliant code formatting with Laravel-specific rules
- **EditorConfig**: Consistent editor behavior across different IDEs
- **DevContainer Integration**: All extensions and settings are automatically installed

#### How It Works:
1. **On Save**: Code is automatically formatted when you save any PHP file
2. **Manual Formatting**: Use `Ctrl+Shift+P` → "Format Document" or `Shift+Alt+F`
3. **VS Code Tasks**: Use `Ctrl+Shift+P` → "Tasks: Run Task" → Choose formatting task


### Troubleshooting

If you can't access the documentation:

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

## License

This project is private. All rights reserved.
