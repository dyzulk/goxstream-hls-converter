import * as p from '@clack/prompts';
import { runProcess } from '@/utils/exec';
import { isCommandAvailable } from '@/utils/checkEnv';
import path from 'path';

interface RunnerConfig {
  value: string;
  label: string;
  cmd: string;
  args: string[];
  dir?: string;
  reqs: string[];
}

const RUNNERS: RunnerConfig[] = [
  { value: 'cf-container', label: 'Cloudflare Container Worker (cf-container)', cmd: 'pnpm', args: ['--filter', 'cf-container', 'start'], reqs: ['node'] },
  { value: 'go-native', label: 'Go Native Runner (go-native)', cmd: process.platform === 'win32' ? '.\\dist\\transcode-server.exe' : './dist/transcode-server', args: [], dir: 'packages/go-native', reqs: [] },
  { value: 'node-native', label: 'Node.js Native Runner (node-native)', cmd: 'pnpm', args: ['--filter', 'node-native', 'start'], reqs: ['node'] },
  { value: 'php-native', label: 'PHP Native Runner (php-native)', cmd: 'php', args: ['-S', '0.0.0.0:8080', '-t', 'public'], dir: 'packages/php-native', reqs: ['php'] },
  { value: 'docker-native', label: 'Docker Native Runner (docker-native)', cmd: 'docker', args: ['compose', 'up', '-d'], dir: 'packages/docker-native', reqs: ['docker'] }
];

export async function runStart(runnerName?: string): Promise<void> {
  const rootDir = process.cwd();
  let selected = runnerName;

  if (!selected) {
    const choice = await p.select({
      message: 'Select a runner to start in production mode:',
      options: RUNNERS.map(r => ({ value: r.value, label: r.label }))
    });

    if (p.isCancel(choice)) {
      p.log.warn('Operation cancelled.');
      return;
    }

    selected = choice as string;
  }

  const runner = RUNNERS.find(r => r.value === selected);
  if (!runner) {
    p.log.error(`Unknown runner: ${selected}`);
    process.exit(1);
  }

  // Check requirements
  for (const req of runner.reqs) {
    if (!isCommandAvailable(req)) {
      p.log.error(`Required tool "${req}" is not installed/available in PATH.`);
      process.exit(1);
    }
  }

  const targetDir = runner.dir ? path.join(rootDir, runner.dir) : rootDir;
  p.log.info(`Starting ${runner.value} in production mode...`);
  const code = await runProcess(runner.cmd, runner.args, targetDir);
  if (code !== 0 && code !== null) {
    p.log.error(`Runner ${runner.value} failed to start or stopped with exit code ${code}`);
  }
}
