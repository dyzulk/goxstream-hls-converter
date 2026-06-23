import http from 'http';
import { getOrCreateJob } from './services/jobs.js';
import { runTranscoding } from './services/transcoder.js';
import { handleUpgrade } from './ws/gateway.js';

// HTTP Server Creation
const server = http.createServer((req, res) => {
  const host = req.headers.host || 'localhost';
  const url = new URL(req.url || '', `http://${host}`);
  
  if (url.pathname === '/health' && req.method === 'GET') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ status: 'healthy', service: 'video-converter' }));
    return;
  }

  if (url.pathname === '/transcode' && req.method === 'POST') {
    let body = '';
    req.on('data', chunk => body += chunk);
    req.on('end', () => {
      try {
        const payload = JSON.parse(body);
        const { job_id, input_url, upload_url_prefix } = payload;
        
        if (!job_id || !input_url || !upload_url_prefix) {
          res.writeHead(400, { 'Content-Type': 'text/plain' });
          res.end('job_id, input_url, and upload_url_prefix are required');
          return;
        }

        const job = getOrCreateJob(job_id);
        // Execute background transcode
        runTranscoding(job_id, input_url, upload_url_prefix, job);

        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ status: 'processing', job_id }));
      } catch (err) {
        res.writeHead(400, { 'Content-Type': 'text/plain' });
        res.end('Invalid JSON body');
      }
    });
    return;
  }

  res.writeHead(404, { 'Content-Type': 'text/plain' });
  res.end('Not Found');
});

// Delegate WebSocket Upgrade
server.on('upgrade', (request, socket, head) => {
  handleUpgrade(request, socket, head);
});

// Listen
const port = process.env.PORT || 8080;
server.listen(port, () => {
  console.log(`Goxstream Node Native Server (TypeScript) listening on :${port}`);
});

