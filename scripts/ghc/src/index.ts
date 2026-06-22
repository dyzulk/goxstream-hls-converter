import { Command } from 'commander';
import * as p from '@clack/prompts';
import { runCheck } from '@/commands/check';
import { runInstall } from '@/commands/install';
import { runInstallFfmpeg } from '@/commands/installFfmpeg';
import { runDev } from '@/commands/dev';
import { runBuild } from '@/commands/build';
import { runStart } from '@/commands/start';

const program = new Command();

program
  .name('ghc')
  .description('Goxstream HLS Converter management utility');

program
  .command('check')
  .description('Check if required binaries are installed (go, php, composer, ffmpeg, docker)')
  .action(async () => {
    p.intro('Goxstream HLS Converter: Environment Check');
    const success = await runCheck();
    if (success) {
      p.outro('Environment check passed.');
    } else {
      p.outro('Environment check failed. Please install the missing required packages.');
      process.exit(1);
    }
  });

program
  .command('install')
  .description('Install dependencies across all packages')
  .action(async () => {
    p.intro('Goxstream HLS Converter: Install Dependencies');
    await runInstall();
    p.log.info('Running local FFmpeg installer...');
    await runInstallFfmpeg();
    p.outro('Installation sequence completed.');
  });

program
  .command('install-ffmpeg')
  .description('Download and install pinned FFmpeg 8.1 static binaries locally')
  .action(async () => {
    p.intro('Goxstream HLS Converter: Install Local FFmpeg');
    await runInstallFfmpeg();
    p.outro('Local FFmpeg setup completed.');
  });

program
  .command('dev [runner]')
  .description('Start a runner in development mode')
  .action(async (runner) => {
    p.intro('Goxstream HLS Converter: Dev Mode');
    await runDev(runner);
  });

program
  .command('build [runner]')
  .description('Build compiled packages or Docker containers')
  .action(async (runner) => {
    p.intro('Goxstream HLS Converter: Build');
    await runBuild(runner);
  });

program
  .command('start [runner]')
  .description('Start a runner in production mode')
  .action(async (runner) => {
    p.intro('Goxstream HLS Converter: Start (Production)');
    await runStart(runner);
  });

async function main() {
  if (process.argv.length <= 2) {
    p.intro('Goxstream HLS Converter Manager (GHC)');
    
    const action = await p.select({
      message: 'What action would you like to perform?',
      options: [
        { value: 'check', label: 'Check system requirements (check)' },
        { value: 'install', label: 'Install package dependencies (install)' },
        { value: 'install-ffmpeg', label: 'Install local FFmpeg static binaries (install-ffmpeg)' },
        { value: 'dev', label: 'Start a runner in development mode (dev)' },
        { value: 'build', label: 'Build production bundles/binaries (build)' },
        { value: 'start', label: 'Start a runner in production mode (start)' },
        { value: 'exit', label: 'Exit' }
      ]
    });

    if (p.isCancel(action) || action === 'exit') {
      p.outro('Goodbye.');
      return;
    }

    switch (action) {
      case 'check':
        const success = await runCheck();
        if (success) {
          p.outro('Environment check passed.');
        } else {
          p.outro('Environment check failed.');
        }
        break;
      case 'install':
        await runInstall();
        p.log.info('Running local FFmpeg installer...');
        await runInstallFfmpeg();
        p.outro('Installation completed.');
        break;
      case 'install-ffmpeg':
        await runInstallFfmpeg();
        p.outro('Local FFmpeg setup completed.');
        break;
      case 'dev':
        await runDev();
        break;
      case 'build':
        await runBuild();
        break;
      case 'start':
        await runStart();
        break;
    }
  } else {
    program.parse(process.argv);
  }
}

main().catch((err) => {
  p.log.error(`Execution failed: ${err.message}`);
  process.exit(1);
});
