# Goxstream Node Native Transcoder Runner (`node-native`)

This package is a TypeScript/Node.js-based video transcoding runner. It implements native HTTP/HTTPS stream handling for low-memory footprint downloading and uploading, along with a built-in WebSocket server to broadcast real-time transcoding progress.

---

## Prerequisites

* **Node.js**: Version 18.x or 22.x (recommended).
* **PNPM**: Package manager.
* **FFmpeg**: FFmpeg and FFprobe must be installed on your host machine and accessible via system PATH.

---

## Configuration

Environment variables can be configured in a `.env` file inside this directory:

| Variable | Description | Default |
|----------|-------------|---------|
| `PORT` | Port for the HTTP and WebSocket server | `8080` |
| `GOX_FFMPEG_PATH` | Path to the `ffmpeg` executable | `ffmpeg` |
| `GOX_FFPROBE_PATH` | Path to the `ffprobe` executable | `ffprobe` |

---

## API Endpoints

### 1. HTTP GET `/health`
Returns service status:
```json
{
  "status": "healthy",
  "service": "video-converter"
}
```

### 2. HTTP POST `/transcode`
Spawns a background transcoding job.
* **Payload**:
  ```json
  {
    "job_id": "episode-xyz",
    "input_url": "http://localhost:3000/api/internal/media/download?key=source.mp4",
    "upload_url_prefix": "http://localhost:3000/api/internal/media/upload/streams/xyz"
  }
  ```

### 3. WebSocket Connection
Allows client connection upgrades to receive live transcode progress frames.

---

## Commands

### Install Dependencies
Run from the root or this directory:
```bash
pnpm install
```

### Local Development
To run directly from source using `tsx`:
```bash
pnpm dev
```
Or via the workspace CLI:
```bash
./ghc dev node-native
```

### Production Build & Run
To compile the TypeScript project and run the output JavaScript:
```bash
pnpm build
pnpm start
```
Or via the workspace CLI:
```bash
./ghc build node-native
./ghc start node-native
```
