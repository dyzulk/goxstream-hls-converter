# Goxstream PHP Laravel Transcoder Runner (`php-laravel`)

This package is a full-featured video transcoding runner built on top of the Laravel 13 framework. It features Artisan console commands, Laravel Reverb for WebSocket events broadcasting, Pest for automated testing, and Larastan for static type analysis.

---

## Prerequisites

* **PHP Engine**: PHP 8.5 or newer.
* **Composer**: PHP package dependency manager.
* **FFmpeg**: FFmpeg and FFprobe must be installed on your host machine and accessible via system PATH.

---

## Getting Started

### 1. Install Dependencies
Run composer install inside this folder:
```bash
composer install
```

### 2. Configure Environment
Copy the example environment configuration and generate the application key:
```bash
cp .env.example .env
php artisan key:generate
```

---

## Configuration

Settings are configured via `.env` or configurations under `config/services.php`:

| Variable | Description | Default |
|----------|-------------|---------|
| `PORT` | Port for the Laravel HTTP application server | `8080` |
| `REVERB_PORT` | Port for the Laravel Reverb WebSocket server | `8081` |
| `GOX_FFMPEG_PATH` | Path to the `ffmpeg` executable | `ffmpeg` |
| `GOX_FFPROBE_PATH` | Path to the `ffprobe` executable | `ffprobe` |

---

## Commands

### Local Development
To launch the HTTP server, queue listener, and Reverb WebSocket server concurrently:
```bash
composer dev
```
Or via the workspace CLI:
```bash
./ghc dev php-laravel
```

### Run Automated Tests (Pest/PHPUnit)
To verify that everything is functioning correctly:
```bash
composer test
```
Or:
```bash
php artisan test
```

### Run Static Analysis (PHPStan)
To verify codebase type safety:
```bash
composer types:check
```

### Code Formatting (Laravel Pint)
To format coding style:
```bash
composer lint
```
