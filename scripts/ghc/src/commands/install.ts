import * as p from '@clack/prompts';
import { runProcess } from '@/utils/exec';
import { isCommandAvailable } from '@/utils/checkEnv';
import path from 'path';

export async function runInstall(): Promise<void> {
  const rootDir = process.cwd();
  
  // 1. Install Node.js dependencies
  const s1 = p.spinner();
  s1.start('Installing workspace root dependencies (pnpm install)...');
  const nodeCode = await runProcess('pnpm', ['install'], rootDir);
  if (nodeCode === 0) {
    s1.stop('Workspace root dependencies installed successfully.');
  } else {
    s1.stop('Failed to install workspace root dependencies.');
    process.exit(1);
  }

  // 2. Install PHP Composer dependencies (if Composer is present)
  if (isCommandAvailable('composer') && isCommandAvailable('php')) {
    const s2 = p.spinner();
    s2.start('Installing php-native dependencies (composer install)...');
    const phpNativeDir = path.join(rootDir, 'packages/php-native');
    const phpCode = await runProcess('composer', ['install'], phpNativeDir);
    if (phpCode === 0) {
      s2.stop('PHP native dependencies installed successfully.');
    } else {
      s2.stop('Failed to install PHP native dependencies.');
      process.exit(1);
    }
  } else {
    p.log.warn('Composer or PHP is not available. Skipping php-native package installation.');
  }
}
