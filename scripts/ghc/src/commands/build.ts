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
  { value: 'cf-container', label: 'Cloudflare Container Worker (cf-container)', cmd: 'pnpm', args: ['--filter', 'cf-container', 'build'], reqs: ['node'] },
  { value: 'go-native', label: 'Go Native Runner (go-native)', cmd: 'go', args: ['build', '-o', 'dist/transcode-server', 'main.go'], dir: 'packages/go-native', reqs: ['go'] },
  { value: 'node-native', label: 'Node.js Native Runner (node-native)', cmd: 'pnpm', args: ['--filter', 'node-native', 'build'], reqs: ['node'] },
  { value: 'docker-native', label: 'Docker Native Runner (docker-native)', cmd: 'docker', args: ['compose', 'build'], dir: 'packages/docker-native', reqs: ['docker'] },
  { value: 'php-native', label: 'PHP Native Runner (php-native)', cmd: 'echo', args: ['PHP has no build step.'], reqs: [] }
];

export async function runBuild(runnerName?: string): Promise<void> {
  const rootDir = process.cwd();
  let selected = runnerName;

  if (!selected) {
    const choice = await p.select({
      message: 'Select a runner to build:',
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
  p.log.info(`Building ${runner.value}...`);
  const code = await runProcess(runner.cmd, runner.args, targetDir);
  if (code === 0) {
    p.log.success(`Successfully built ${runner.value}`);
  } else {
    p.log.error(`Failed to build ${runner.value} (exit code ${code})`);
  }
}
