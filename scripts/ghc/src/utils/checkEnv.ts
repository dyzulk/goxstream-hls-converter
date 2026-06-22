import { execSync } from 'child_process';
import path from 'path';
import fs from 'fs';

export function isCommandAvailable(command: string): boolean {
  // 1. Check workspace-local bin first
  const localExt = process.platform === 'win32' ? '.exe' : '';
  const localPath = path.join(process.cwd(), 'bin', `${command}${localExt}`);
  if (fs.existsSync(localPath)) {
    return true;
  }

  // 2. Fallback to system path
  try {
    const cmd = process.platform === 'win32' ? `where.exe ${command}` : `which ${command}`;
    execSync(cmd, { stdio: 'ignore' });
    return true;
  } catch {
    return false;
  }
}
