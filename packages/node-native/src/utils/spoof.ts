import http from 'http';

export function initializeSpoof(): void {
  const internalHost = process.env.GOX_R2_INTERNAL_HOST || 'r2.internal';
  const mockTarget = process.env.GOX_R2_MOCK_RESOLVE_TARGET || '127.0.0.1:3000';
  const [mockIp, mockPort] = mockTarget.split(':');

  const originalRequest = http.request;
  
  // @ts-ignore - overriding core http.request signature safely
  http.request = function (options: any, callback?: any) {
    let opts = options;
    if (typeof options === 'string') {
      opts = new URL(options);
    } else if (options instanceof URL) {
      opts = options;
    }
    
    const hostname = opts.hostname || opts.host;
    if (hostname === internalHost) {
      opts.hostname = mockIp;
      opts.port = mockPort || '80';
      if (opts.headers) {
        opts.headers['Host'] = internalHost;
      } else {
        opts.headers = { Host: internalHost };
      }
    }
    return originalRequest.call(this, opts, callback);
  };
}
