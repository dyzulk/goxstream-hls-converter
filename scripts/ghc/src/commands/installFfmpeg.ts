import * as p from '@clack/prompts';
import { FFMPEG_DOWNLOAD_URLS, FFMPEG_VERSION } from '@/utils/config';
import fs from 'fs';
import path from 'path';
import https from 'https';
import AdmZip from 'adm-zip';

function downloadFile(url: string, destPath: string): Promise<void> {
  return new Promise((resolve, reject) => {
    https.get(url, (response) => {
      const { statusCode, headers } = response;

      // Handle redirects (critical for GitHub Release assets redirects to S3)
      if (statusCode && statusCode >= 300 && statusCode < 400 && headers.location) {
        downloadFile(headers.location, destPath).then(resolve).catch(reject);
        return;
      }

      if (statusCode !== 200) {
        reject(new Error(`Server responded with status code ${statusCode}`));
        return;
      }

      const fileStream = fs.createWriteStream(destPath);
      response.pipe(fileStream);

      fileStream.on('finish', () => {
        fileStream.close();
        resolve();
      });

      fileStream.on('error', (err) => {
        fs.unlink(destPath, () => {});
        fileStream.close();
        reject(err);
      });
    }).on('error', (err) => {
      reject(err);
    });
  });
}

export async function runInstallFfmpeg(): Promise<void> {
  const platform = process.platform;
  const arch = process.arch;

  p.log.info(`System platform detected: ${platform}-${arch}`);

  const platformUrls = FFMPEG_DOWNLOAD_URLS[platform];
  if (!platformUrls) {
    p.log.error(`No FFmpeg binaries available for platform: ${platform}`);
    process.exit(1);
  }

  const url = platformUrls[arch] || platformUrls['x64']; // fallback to x64 if architecture specific is not found
  if (!url) {
    p.log.error(`No FFmpeg binaries available for architecture: ${arch}`);
    process.exit(1);
  }

  const binDir = path.join(process.cwd(), 'bin');
  if (!fs.existsSync(binDir)) {
    fs.mkdirSync(binDir, { recursive: true });
  }

  const zipPath = path.join(binDir, 'tmp-ffmpeg.zip');

  const s = p.spinner();
  s.start(`Downloading FFmpeg ${FFMPEG_VERSION} static builds from GitHub...`);

  try {
    await downloadFile(url, zipPath);
    s.message('Extracting zip archive...');
    
    const zip = new AdmZip(zipPath);
    zip.extractAllTo(binDir, true);

    s.message('Cleaning up temporary files...');
    fs.unlinkSync(zipPath);

    // Apply execution permissions on macOS/Linux
    if (platform !== 'win32') {
      s.message('Applying execution permissions...');
      const ffmpegPath = path.join(binDir, 'ffmpeg');
      const ffprobePath = path.join(binDir, 'ffprobe');
      
      if (fs.existsSync(ffmpegPath)) {
        fs.chmodSync(ffmpegPath, 0o755);
      }
      if (fs.existsSync(ffprobePath)) {
        fs.chmodSync(ffprobePath, 0o755);
      }
    }

    s.stop(`Successfully installed FFmpeg ${FFMPEG_VERSION} to ${binDir}`);
  } catch (error: any) {
    s.stop('Failed to download/install FFmpeg.');
    if (fs.existsSync(zipPath)) {
      try { fs.unlinkSync(zipPath); } catch {}
    }
    p.log.error(`Error: ${error.message}`);
    process.exit(1);
  }
}
