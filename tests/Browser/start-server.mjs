import { spawn, spawnSync } from 'node:child_process';
import { resolve, sep, extname } from 'node:path';
import { mkdirSync, closeSync, openSync, existsSync, statSync, realpathSync, createReadStream } from 'node:fs';
import { createServer, request as proxyRequest } from 'node:http';

if (existsSync(resolve('bootstrap/cache/config.php'))) throw new Error('Clear cached configuration before running browser tests.');
const database = resolve('storage/framework/testing/adaptive-browser.sqlite');
mkdirSync(resolve('storage/framework/testing'), { recursive: true });
closeSync(openSync(database, 'a'));
const env = { ...process.env, APP_ENV: 'testing', APP_DEBUG: 'true', APP_URL: 'http://127.0.0.1:8184',
    APP_KEY: 'base64:' + Buffer.alloc(32, 4).toString('base64'), ADAPTIVE_BROWSER_TEST: '1',
    DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', SESSION_DRIVER: 'file',
    SESSION_SECURE_COOKIE: 'false', SESSION_DOMAIN: '',
    CACHE_STORE: 'array', BCRYPT_ROUNDS: '4', NOTIFICATIONS_DRY_RUN: 'true', BULKSMS_NIGERIA_DRY_RUN: 'true',
    QUEUE_CONNECTION: 'sync', BROADCAST_CONNECTION: 'log', MAIL_MAILER: 'array' };
const migrated = spawnSync('php', ['tests/Browser/prepare.php'], { env, stdio: 'inherit' });
if (migrated.status !== 0) process.exit(migrated.status || 1);

// Node handles static files/browser preconnections; PHP receives only complete application requests.
// This avoids idle Chrome connections blocking PHP's single-threaded Windows development server.
const php = spawn('php', ['-S', '127.0.0.1:8185', '-t', 'public', 'tests/Browser/router.php'], { env, stdio: 'inherit' });
const publicRoot = realpathSync(resolve('public'));
const mime = { '.js': 'text/javascript; charset=utf-8', '.css': 'text/css; charset=utf-8', '.json': 'application/json',
    '.png': 'image/png', '.jpg': 'image/jpeg', '.svg': 'image/svg+xml', '.woff2': 'font/woff2', '.ico': 'image/x-icon' };
const server = createServer((incoming, outgoing) => {
    const pathname = decodeURIComponent(new URL(incoming.url, 'http://127.0.0.1:8184').pathname);
    const candidate = resolve(publicRoot, '.' + pathname);
    if (candidate.startsWith(publicRoot + sep) && existsSync(candidate) && statSync(candidate).isFile()) {
        const file = realpathSync(candidate);
        if (!file.startsWith(publicRoot + sep)) { outgoing.writeHead(404).end(); return; }
        outgoing.writeHead(200, { 'Content-Type': mime[extname(file)] || 'application/octet-stream' });
        createReadStream(file).pipe(outgoing);
        return;
    }
    const chunks = [];
    incoming.on('data', chunk => chunks.push(chunk));
    incoming.on('end', () => {
        const body = Buffer.concat(chunks);
        const forwarded = proxyRequest({ host: '127.0.0.1', port: 8185, path: incoming.url,
            method: incoming.method, agent: false, headers: { ...incoming.headers, connection: 'close', 'content-length': body.length } }, response => {
            outgoing.writeHead(response.statusCode || 502, response.headers);
            response.pipe(outgoing);
        });
        forwarded.on('error', () => { if (!outgoing.headersSent) outgoing.writeHead(502); outgoing.end('Test PHP server unavailable.'); });
        forwarded.end(body);
    });
});
server.listen(8184, '127.0.0.1');
const stop = () => { server.close(); php.kill(); };
process.on('SIGTERM', stop);
process.on('SIGINT', stop);
php.on('exit', code => { server.close(); process.exit(code || 0); });
