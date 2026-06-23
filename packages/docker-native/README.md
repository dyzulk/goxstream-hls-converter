# Goxstream Docker Native Transcoder Runner (`docker-native`)

This package provides a Dockerized container wrapping the high-performance Go native transcoding server. It compiles the Go server and packs it together with Alpine Linux and native `ffmpeg` binaries.

---

## Prerequisites

* **Docker**: Install Docker Engine / Desktop.
* **Docker Compose**: Required for running via docker-compose configuration.

---

## Getting Started

### 1. Build the Docker Image
To build the image locally:
```bash
docker build -t goxstream-docker-native .
```
Or via the workspace CLI tool:
```bash
./ghc build docker-native
```

### 2. Run the Container
Run the container mapping port 8080:
```bash
docker run -d -p 8080:8080 --name transcoder-docker goxstream-docker-native
```
Or via the workspace CLI tool:
```bash
./ghc dev docker-native
```

---

## Running with Docker Compose

You can start the service in the background using Docker Compose:
```bash
docker compose up -d
```
To stop the service:
```bash
docker compose down
```

The transcoder will listen on `http://localhost:8080`.
