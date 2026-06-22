import http from 'http';
import fs from 'fs';

export function downloadFile(url: string, destPath: string): Promise<void> {
  return new Promise((resolve, reject) => {
    http.get(url, (res) => {
      if (res.statusCode !== 200) {
        reject(new Error(`Failed to download: status code ${res.statusCode}`));
        return;
      }
      const fileStream = fs.createWriteStream(destPath);
      res.pipe(fileStream);
      fileStream.on('finish', () => {
        fileStream.close();
        resolve();
      });
      fileStream.on('error', reject);
    }).on('error', reject);
  });
}

export function uploadFile(localPath: string, r2URL: string): Promise<void> {
  return new Promise((resolve, reject) => {
    const fileStream = fs.createReadStream(localPath);
    const stats = fs.statSync(localPath);
    
    let contentType = 'application/octet-stream';
    if (localPath.endsWith('.m3u8')) {
      contentType = 'application/x-mpegURL';
    } else if (localPath.endsWith('.ts')) {
      contentType = 'video/MP2T';
    }

    const url = new URL(r2URL);
    const req = http.request({
      hostname: url.hostname,
      port: url.port,
      path: url.pathname + url.search,
      method: 'PUT',
      headers: {
        'Content-Length': stats.size,
        'Content-Type': contentType
      }
    }, (res) => {
      if (res.statusCode !== 200) {
        let body = '';
        res.on('data', chunk => body += chunk);
        res.on('end', () => reject(new Error(`Upload failed: status ${res.statusCode}, body: ${body}`)));
      } else {
        resolve();
      }
    });

    req.on('error', reject);
    fileStream.pipe(req);
  });
}
