import { WebSocketServer } from 'ws';
import { IncomingMessage } from 'http';
import { Duplex } from 'stream';
import { getOrCreateJob } from '../services/jobs.js';

export const wss = new WebSocketServer({ noServer: true });

export function handleUpgrade(request: IncomingMessage, socket: Duplex, head: Buffer): void {
  const host = request.headers.host || 'localhost';
  const url = new URL(request.url || '', `http://${host}`);
  
  if (url.pathname === '/ws') {
    const jobId = url.searchParams.get('job_id');
    if (!jobId) {
      socket.write('HTTP/1.1 400 Bad Request\r\n\r\n');
      socket.destroy();
      return;
    }

    wss.handleUpgrade(request, socket, head, (ws) => {
      const job = getOrCreateJob(jobId);
      job.clients.add(ws);

      // Send initial state immediately
      ws.send(JSON.stringify({
        job_id: job.job_id,
        status: job.status,
        progress: job.progress,
        error: job.error
      }));

      ws.on('close', () => {
        job.clients.delete(ws);
      });
    });
  } else {
    socket.destroy();
  }
}
