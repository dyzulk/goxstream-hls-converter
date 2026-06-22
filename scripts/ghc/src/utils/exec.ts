import { spawn } from 'child_process';
import path from 'path';
import fs from 'fs';

export function runProcess(command: string, args: string[], cwd: string): Promise<number | null> {
  return new Promise((resolve) => {
    const localBinDir = path.join(process.cwd(), 'bin');
    const localExt = process.platform === 'win32' ? '.exe' : '';
    
    const ffmpegPath = path.join(localBinDir, `ffmpeg${localExt}`);
    const ffprobePath = path.join(localBinDir, `ffprobe${localExt}`);

    const env = { ...process.env };
    if (fs.existsSync(ffmpegPath)) {
      env.GOX_FFMPEG_PATH = ffmpegPath;
    }
    if (fs.existsSync(ffprobePath)) {
      env.GOX_FFPROBE_PATH = ffprobePath;
    }

    const child = spawn(command, args, {
      cwd,
      stdio: 'inherit',
      shell: true,
      env,
    });

    child.on('close', (code) => {
      resolve(code);
    });

    child.on('error', (err) => {
      console.error(`Failed to start command: ${command} ${args.join(' ')}:`, err.message);
      resolve(1);
    });
  });
}
