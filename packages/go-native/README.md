# Goxstream Go Native Transcoder Runner (`go-native`)

This package is a high-performance video transcoding runner written in Go. It uses Go's native concurrency models (goroutines) to handle background transcoding while serving status monitoring via HTTP and WebSockets.

---

## Prerequisites

* **Go Compiler**: Go 1.20 or newer.
* **FFmpeg**: FFmpeg and FFprobe must be installed on your machine and added to your system's PATH.

---

## Configuration

Environment variables can be configured in a `.env` file in this directory or exported in your shell:

| Variable | Description | Default |
|----------|-------------|---------|
| `PORT` | Port for the HTTP and WebSocket server | `8080` |
| `GOX_FFMPEG_PATH` | Custom path to the `ffmpeg` executable | `ffmpeg` |
| `GOX_FFPROBE_PATH` | Custom path to the `ffprobe` executable | `ffprobe` |

---

## API Endpoints

### 1. HTTP GET `/health`
Returns the status of the service:
```json
{
  "status": "healthy",
  "service": "video-converter"
}
```

### 2. HTTP POST `/transcode`
Starts an asynchronous background transcoding job.
* **Payload**:
  ```json
  {
    "job_id": "episode-abc",
    "input_url": "http://localhost:3000/api/internal/media/download?key=source.mp4",
    "upload_url_prefix": "http://localhost:3000/api/internal/media/upload/streams/abc"
  }
  ```
* **Response**:
  ```json
  {
    "status": "processing",
    "job_id": "episode-abc"
  }
  ```

### 3. WebSocket `/ws?job_id={job_id}`
Connects to stream real-time progress update events for the specified job.

---

## Commands

### Run in Development
To run the server locally:
```bash
go run main.go
```
Or via the workspace CLI tool:
```bash
./ghc dev go-native
```

### Compile to Binary
To compile the package into a production-ready binary:
```bash
go build -o go-native.exe main.go
```
Or via the workspace CLI:
```bash
./ghc build go-native
```
