import { Container } from "@cloudflare/containers";
import { Hono } from "hono";

interface Env {
  GOXSTREAM_CONTAINER: DurableObjectNamespace<GoxstreamContainer>;
}

export class GoxstreamContainer extends Container<Env> {
  defaultPort = 8080;
  sleepAfter = "15m";

  override onStart() {
    console.log("Goxstream Container successfully started");
  }

  override onStop() {
    console.log("Goxstream Container successfully shut down");
  }

  override onError(error: unknown) {
    console.log("Goxstream Container error:", error);
  }
}

const app = new Hono<{ Bindings: Env }>();

app.get("/", (c) => {
  return c.text(
    "GoxStream HLS Converter Worker\n\n" +
      "Endpoints:\n" +
      "ALL /container/:id/* - Proxies requests to a specific container instance\n" +
      "GET /health - Worker health check"
  );
});

app.get("/health", (c) => {
  return c.json({ status: "healthy", service: "hls-converter-worker" });
});

// Wildcard route to proxy all requests to the Durable Object Container
app.all("/container/:id/*", async (c) => {
  const id = c.req.param("id");
  const containerId = c.env.GOXSTREAM_CONTAINER.idFromName(`/container/${id}`);
  const container = c.env.GOXSTREAM_CONTAINER.get(containerId);
  
  // Rewrite the request URL so that the container receives the subpath (e.g. /transcode or /ws)
  const url = new URL(c.req.url);
  const pathParts = url.pathname.split("/").slice(3); // splits "", "container", "id", "subpath..."
  const subpath = "/" + pathParts.join("/");
  
  const containerRequest = new Request("http://localhost" + subpath + url.search, {
    method: c.req.method,
    headers: c.req.raw.headers,
    body: c.req.method !== "GET" && c.req.method !== "HEAD" ? c.req.raw.body : undefined,
    redirect: "manual"
  });
  
  return await container.fetch(containerRequest);
});

export default app;
