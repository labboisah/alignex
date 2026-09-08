import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser', testMatch: '*.spec.ts', fullyParallel: false, workers: 1,
    timeout: 90000, expect: { timeout: 12000 }, reporter: 'list',
    use: { baseURL: 'http://127.0.0.1:8184', browserName: 'chromium', channel: process.env.PLAYWRIGHT_CHANNEL || (process.platform === 'win32' ? 'msedge' : undefined),
        headless: true, launchOptions: { args: ['--use-fake-device-for-media-stream'] }, trace: 'retain-on-failure', screenshot: 'only-on-failure' },
    webServer: { command: 'node tests/Browser/start-server.mjs', url: 'http://127.0.0.1:8184/exam/login',
        reuseExistingServer: false, timeout: 120000 },
});
