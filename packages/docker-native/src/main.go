package main

import (
	"bufio"
	"context"
	"log"
	"net"
	"net/http"
	"os"
	"strings"
	"time"
	"video-converter/pkg"
)

func loadEnv(filepath string) {
	file, err := os.Open(filepath)
	if err != nil {
		return
	}
	defer file.Close()

	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		line := strings.TrimSpace(scanner.Text())
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		parts := strings.SplitN(line, "=", 2)
		if len(parts) == 2 {
			key := strings.TrimSpace(parts[0])
			val := strings.TrimSpace(parts[1])
			if (strings.HasPrefix(val, "\"") && strings.HasSuffix(val, "\"")) ||
				(strings.HasPrefix(val, "'") && strings.HasSuffix(val, "'")) {
				val = val[1 : len(val)-1]
			}
			os.Setenv(key, val)
		}
	}
}

func main() {
	// Load .env if present
	loadEnv(".env")

	port := os.Getenv("PORT")
	if port == "" {
		port = "8080"
	}

	internalHost := os.Getenv("GOX_R2_INTERNAL_HOST")
	if internalHost == "" {
		internalHost = "r2.internal"
	}

	mockTarget := os.Getenv("GOX_R2_MOCK_RESOLVE_TARGET")

	if mockTarget != "" {
		log.Printf("Redirecting requests for %s to %s", internalHost, mockTarget)
		dialer := &net.Dialer{
			Timeout:   30 * time.Second,
			KeepAlive: 30 * time.Second,
		}
		http.DefaultClient.Transport = &http.Transport{
			DialContext: func(ctx context.Context, network, addr string) (net.Conn, error) {
				host, port, err := net.SplitHostPort(addr)
				if err == nil && host == internalHost {
					if !strings.Contains(mockTarget, ":") {
						addr = net.JoinHostPort(mockTarget, port)
					} else {
						addr = mockTarget
					}
					log.Printf("Dialing redirected target: %s (originally %s)", addr, host)
				}
				return dialer.DialContext(ctx, network, addr)
			},
		}
	}

	http.HandleFunc("/transcode", pkg.HandleTranscode)
	http.HandleFunc("/ws", pkg.HandleWebSocket)
	http.HandleFunc("/health", pkg.HandleHealth)

	log.Printf("Goxstream Go Docker Native Server listening on :%s\n", port)
	if err := http.ListenAndServe(":"+port, nil); err != nil {
		log.Fatalf("Server failed to start: %v", err)
	}
}
