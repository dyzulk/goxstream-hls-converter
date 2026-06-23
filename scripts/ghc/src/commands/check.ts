import * as p from '@clack/prompts';
import { isCommandAvailable } from '@/utils/checkEnv';

export async function runCheck(): Promise<boolean> {
  const dependencies = [
    { name: 'Node.js', command: 'node', required: true },
    { name: 'pnpm', command: 'pnpm', required: true },
    { name: 'Go', command: 'go', required: false, purpose: 'Required for go-native and cf-container' },
    { name: 'PHP', command: 'php', required: false, purpose: 'Required for php-native and php-laravel' },
    { name: 'Composer', command: 'composer', required: false, purpose: 'Required for php-native and php-laravel' },
    { name: 'FFmpeg', command: 'ffmpeg', required: false, purpose: 'Required for local transcoding runners' },
    { name: 'Docker', command: 'docker', required: false, purpose: 'Required for docker-native' }
  ];

  let allRequiredFound = true;
  for (const dep of dependencies) {
    const found = isCommandAvailable(dep.command);
    if (found) {
      p.log.success(`${dep.name}: Available`);
    } else {
      if (dep.required) {
        allRequiredFound = false;
        p.log.error(`${dep.name}: NOT FOUND (Required)`);
      } else {
        p.log.warn(`${dep.name}: NOT FOUND - ${dep.purpose}`);
      }
    }
  }

  return allRequiredFound;
}
