<img src="https://github.com/user-attachments/assets/6ea788c4-f033-45b8-8701-bae663d57119" width="400">

## App Padeliz Back

Service Laravel-based API for https://padeliz.ro

## Development Setup (Windows)

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling.

### Prerequisites

- [Git](https://git-scm.com/downloads)
- [Docker Desktop for Windows](https://www.docker.com/products/docker-desktop/)
- WSL2 enabled (Windows Subsystem for Linux)
- A code editor (recommended: [VS Code](https://code.visualstudio.com/))

### First-time setup

1. **Install VS Code + “PHP Intelephense” extension**

2. **Clone the repository**
   ```bash
   ## in the VSCode terminal
   git clone https://github.com/padeliz/app-padeliz-back.git
   ```

3. **Open the repository folder in VS Code**

3. **Environment Setup**
   ```bash
   # Copy the environment file
   copy .env.example .env
   ```

4. **Start Docker containers**
   ```bash
   # Build and start the containers
   docker-compose up -d

   # View logs (optional)
   docker-compose logs -f
   ```

5. **Install Dependencies**
   ```bash
   # Enter the app container
   docker exec -it laravel_app bash

   # Install PHP dependencies
   composer install

   # Generate application key
   php artisan key:generate
   ```

6. **Database Setup**
   ```bash
   # Run migrations
   docker exec -it laravel_app php artisan migrate

   # Seed the database
   # ⚠️ WARNING: Never run seeders in production! They will erase all existing data.
   docker exec -it laravel_app php artisan db:seed
   ```

### Useful Commands

```bash
# Start containers (run the app)
docker-compose up -d # without -d it will show logs

# Stop containers
docker-compose down

# Rebuild containers
docker-compose up -d --build

# View logs
docker-compose logs -f

# Access MySQL CLI (password is in the .env file)
docker exec -it laravel_db mysql -u db_user -p

# Run artisan commands
docker exec -it laravel_app php artisan [command]

# Run tests
docker exec -it laravel_app php artisan test
```

### Project Structure

  ```bash
  ├── app/               # Application core code
  ├── database/          # Migrations and seeders
  ├── docker/            # Docker configuration files
  ├── routes/            # API routes
  ├── tests/             # Test files
  └── docker-compose.yml # Docker services configuration
  ```

### Available Endpoints

The API is available at `http://localhost:8000`

Its Swagger docs is available at `http://localhost:8000/api/documentation`

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
   docker exec -it laravel_app php artisan test
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
