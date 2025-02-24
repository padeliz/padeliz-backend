<img src="https://github.com/user-attachments/assets/6ea788c4-f033-45b8-8701-bae663d57119" width="400">

## Padel Events API

Service API for https://padel-events.ro

## Development Setup (Windows)

### Prerequisites

- [Git](https://git-scm.com/downloads)
- [Docker Desktop for Windows](https://www.docker.com/products/docker-desktop/)
- WSL2 enabled (Windows Subsystem for Linux)
- A code editor (recommended: [VS Code](https://code.visualstudio.com/))

### Getting Started

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling.

1. **Clone the repository**
   ```bash
   git clone https://github.com/arshavinel/app-padel-events-back.git
   cd app-padel-events-back
   ```

2. **Environment Setup**
   ```bash
   # Copy the environment file
   copy .env.example .env
   ```

3. **Start Docker containers**
   ```bash
   # Build and start the containers
   docker-compose up -d

   # View logs (optional)
   docker-compose logs -f
   ```

4. **Install Dependencies**
   ```bash
   # Enter the app container
   docker exec -it laravel_app bash

   # Install PHP dependencies
   composer install

   # Generate application key
   php artisan key:generate
   ```

5. **Database Setup**
   ```bash
   # Run migrations
   docker exec -it laravel_app php artisan migrate

   # Seed the database (if available)
   ⚠️ **WARNING**: Never run seeders in production! They will erase all existing data.

   docker exec -it laravel_app php artisan db:seed
   ```

### Useful Commands

```bash
# Start containers
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

The API will be available at `http://localhost:8000`

### Database Access

You can connect to the database using these credentials:
- Host: localhost
- Port: 3306
- Database: db_padel_events
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

2. Make your changes and commit
   ```bash
   git add .
   git commit -m "Description of changes"
   ```

3. Push to your branch
   ```bash
   git push origin feature/your-feature-name
   ```

4. Create a Pull Request on GitHub

## API Documentation

The API documentation is generated using L5-Swagger (OpenAPI). To access it:

1. Install dependencies:
    ```bash
    composer install
    ```

2. Generate the Swagger documentation:
    ```bash
    php artisan l5-swagger:generate
    ```

3. Access the Swagger UI at:
    ```bash
    http://localhost:8000/api/documentation

    # ⚠️ Note: If you're using a different port or host, adjust the URL accordingly.
    ```

3. If you make changes to the API endpoints or models, regenerate the documentation:
    ```bash
    php artisan l5-swagger:generate
    ```

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
