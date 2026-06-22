export const FFMPEG_VERSION = '8.1';

export const FFMPEG_DOWNLOAD_URLS: Record<string, Record<string, string>> = {
  win32: {
    x64: 'https://github.com/Tyrrrz/FFmpegBin/releases/download/8.1/ffmpeg-windows-x64.zip'
  },
  linux: {
    x64: 'https://github.com/Tyrrrz/FFmpegBin/releases/download/8.1/ffmpeg-linux-x64.zip'
  },
  darwin: {
    x64: 'https://github.com/Tyrrrz/FFmpegBin/releases/download/8.1/ffmpeg-osx-x64.zip',
    arm64: 'https://github.com/Tyrrrz/FFmpegBin/releases/download/8.1/ffmpeg-osx-arm64.zip'
  }
};
