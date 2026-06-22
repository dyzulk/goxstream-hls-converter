import { WebSocket } from 'ws';

export interface JobState {
  job_id: string;
  status: 'processing' | 'completed' | 'failed';
  progress: number;
  error: string | null;
  clients: Set<WebSocket>;
}

export const jobs = new Map<string, JobState>();

export function getOrCreateJob(jobId: string): JobState {
  let job = jobs.get(jobId);
  if (!job) {
    job = {
      job_id: jobId,
      status: 'processing',
      progress: 0,
      error: null,
      clients: new Set<WebSocket>()
    };
    jobs.set(jobId, job);
  }
  return job;
}

export function updateProgress(job: JobState, progress: number): void {
  job.progress = progress;
  broadcastJobState(job);
}

export function updateCompleted(job: JobState): void {
  job.status = 'completed';
  job.progress = 100;
  broadcastJobState(job);
  closeClients(job);
}

export function updateError(job: JobState, err: Error): void {
  console.error(`[${job.job_id}] Error:`, err);
  job.status = 'failed';
  job.error = err.message;
  broadcastJobState(job);
  closeClients(job);
}

export function broadcastJobState(job: JobState): void {
  const data = JSON.stringify({
    job_id: job.job_id,
    status: job.status,
    progress: job.progress,
    error: job.error
  });
  for (const client of job.clients) {
    try {
      client.send(data);
    } catch (e) {
      client.close();
      job.clients.delete(client);
    }
  }
}

export function closeClients(job: JobState): void {
  for (const client of job.clients) {
    try {
      client.close();
    } catch (e) {}
  }
  job.clients.clear();
}
