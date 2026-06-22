# Stage 1: Build the Go transcode server
FROM golang:1.22-alpine AS builder
WORKDIR /app
COPY container_src/go.mod ./
COPY container_src/main.go ./
COPY container_src/pkg/ ./pkg/
RUN go build -o transcode-server .

# Stage 2: Create runtime image with ffmpeg
FROM alpine:3.19
RUN apk add --no-cache ffmpeg bash libc6-compat
WORKDIR /app
COPY --from=builder /app/transcode-server .
EXPOSE 8080
ENTRYPOINT ["./transcode-server"]
