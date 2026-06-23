# Goxstream PHP Native Transcoder Runner (`php-native`)

This package is a lightweight, framework-free native PHP implementation of the Goxstream video transcoder runner. It uses PHP's built-in development web server to route incoming triggers and spawns background transcoding processes utilizing native operating system CLI calls.

---

## Prerequisites

* **PHP Engine**: PHP 8.0 or newer.
* **Composer**: Required to generate autoload files.
* **FFmpeg**: FFmpeg and FFprobe must be installed on your host machine and accessible via system PATH.

---

## Getting Started

### 1. Install Dependencies
Generate the PHP autoloader by running:
```bash
composer install
```

### 2. Configure Environment
Create a `.env` file inside this directory:
```env
PORT=8080
GOX_FFMPEG_PATH=ffmpeg
GOX_FFPROBE_PATH=ffprobe
```

---

## Commands

### Run Development Server
To launch PHP's built-in web server routing to `public/index.php`:
```bash
php -S localhost:8080 -t public
```
Or via the workspace CLI tool:
```bash
./ghc dev php-native
```
The server will listen on the port specified in `.env` or default to `8080`.
