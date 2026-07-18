import { app, BrowserWindow, ipcMain, Menu } from 'electron';
import { existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { getDeviceFingerprint, getSafeDeviceInfo } from './deviceFingerprint.js';

const currentDir = dirname(fileURLToPath(import.meta.url));
const appRoot = resolve(currentDir, '..', '..');
const rendererDist = resolve(appRoot, 'dist', 'renderer');
const devServerUrl = process.env.VITE_DEV_SERVER_URL;

let mainWindow: BrowserWindow | null = null;
let examModeActive = false;

if (process.platform === 'win32') {
    app.setAppUserModelId('com.alignex.client-app');
}

app.whenReady().then(async () => {
    Menu.setApplicationMenu(null);
    registerIpcHandlers();
    await createWindow();
});

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
        app.quit();
    }
});

app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
        void createWindow();
    }
});

async function createWindow(): Promise<void> {
    const iconPath = resolve(appRoot, 'public', 'images', 'logo.ico');

    mainWindow = new BrowserWindow({
        width: 1180,
        height: 780,
        minWidth: 960,
        minHeight: 680,
        title: 'AlignEx Client App',
        icon: existsSync(iconPath) ? iconPath : undefined,
        backgroundColor: '#F8FAFC',
        autoHideMenuBar: true,
        webPreferences: {
            contextIsolation: true,
            nodeIntegration: false,
            sandbox: true,
            preload: join(currentDir, 'preload.js'),
        },
    });

    hardenWindow(mainWindow);
    registerSecurityEventForwarding(mainWindow);

    if (devServerUrl) {
        await waitForDevServer(devServerUrl);
        await mainWindow.loadURL(devServerUrl);
        return;
    }

    await mainWindow.loadFile(join(rendererDist, 'index.html'));
}

function registerIpcHandlers(): void {
    ipcMain.handle('device:get-safe-info', () => getSafeDeviceInfo(app.getPath('userData')));
    ipcMain.handle('device:get-fingerprint', () => getDeviceFingerprint(app.getPath('userData')));
    ipcMain.handle('exam-mode:enter', () => {
        if (!mainWindow) {
            return { success: false };
        }

        enterExamMode(mainWindow);
        return { success: true };
    });
    ipcMain.handle('exam-mode:exit', () => {
        if (!mainWindow) {
            return { success: false };
        }

        exitExamMode(mainWindow);
        return { success: true };
    });
}

function hardenWindow(window: BrowserWindow): void {
    window.webContents.setWindowOpenHandler(() => ({ action: 'deny' }));

    window.webContents.on('will-navigate', (event) => {
        if (examModeActive) {
            event.preventDefault();
        }
    });

    window.webContents.on('will-redirect', (event) => {
        if (examModeActive) {
            event.preventDefault();
        }
    });

    window.webContents.on('before-input-event', (event, input) => {
        const key = input.key.toLowerCase();
        const devtoolsShortcut = key === 'f12' || (input.control && input.shift && ['i', 'j', 'c'].includes(key));
        const blocked = devtoolsShortcut || (input.control && ['n', 'r', 't', 'w', 'l'].includes(key));

        if (blocked) {
            event.preventDefault();
            if (devtoolsShortcut) {
                window.webContents.send('security:event', { event_type: 'devtools_attempt' });
            }
        }
    });
}

function enterExamMode(window: BrowserWindow): void {
    // MVP lockdown is intentionally configurable and software-only. It raises the barrier
    // for casual misuse, but it cannot replace physical supervision or OS-level kiosk policy.
    examModeActive = true;
    window.setMenuBarVisibility(false);
    window.setAutoHideMenuBar(true);
    window.webContents.closeDevTools();

    if (!window.isFullScreen()) {
        window.setFullScreen(true);
    }

    window.setKiosk(true);
}

function exitExamMode(window: BrowserWindow): void {
    examModeActive = false;
    window.setKiosk(false);

    if (window.isFullScreen()) {
        window.setFullScreen(false);
    }

    window.setMenuBarVisibility(false);
    window.setAutoHideMenuBar(true);
}

function registerSecurityEventForwarding(window: BrowserWindow): void {
    // These checks are software-only signals. They help supervisors audit suspicious behavior,
    // but they are not a complete lockdown substitute for OS policy, kiosk mode, or proctoring.
    window.on('minimize', () => {
        window.webContents.send('security:event', { event_type: 'app_minimized' });
    });

    window.on('leave-full-screen', () => {
        window.webContents.send('security:event', { event_type: 'fullscreen_exit' });
    });

    window.webContents.on('devtools-opened', () => {
        window.webContents.closeDevTools();
        window.webContents.send('security:event', { event_type: 'devtools_attempt' });
    });
}

async function waitForDevServer(url: string): Promise<void> {
    const deadline = Date.now() + 30_000;

    while (Date.now() < deadline) {
        try {
            const response = await fetch(url);

            if (response.ok) {
                return;
            }
        } catch {
            // Vite is still starting.
        }

        await new Promise((resolve) => setTimeout(resolve, 300));
    }

    throw new Error(`Renderer dev server did not become ready at ${url}`);
}
