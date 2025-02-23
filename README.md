<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400"></a></p>

<p align="center">
<a href="https://travis-ci.org/laravel/framework"><img src="https://travis-ci.org/laravel/framework.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling.

## Development Setup (Windows)

### Prerequisites

- [Git](https://git-scm.com/downloads)
- [Docker Desktop for Windows](https://www.docker.com/products/docker-desktop/)
- WSL2 enabled (Windows Subsystem for Linux)
- A code editor (recommended: [VS Code](https://code.visualstudio.com/))

### Getting Started

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
   docker exec -it laravel_app php artisan db:seed
   ```

### Useful Commands

```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# Rebuild containers
docker-compose up -d --build

# View logs
docker-compose logs -f

# Access MySQL CLI
docker exec -it laravel_db mysql -u laravel -p

# Run artisan commands
docker exec -it laravel_app php artisan [command]

# Run tests
docker exec -it laravel_app php artisan test
```

### Project Structure

```
├── app/                 # Application core code
├── database/           # Migrations and seeders
├── docker/            # Docker configuration files
├── routes/            # API routes
├── tests/            # Test files
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

## License

This project is private. All rights reserved.
