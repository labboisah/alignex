import type { BrowserWindow as BrowserWindowType } from 'electron';
import { existsSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { loadConfig } from '../services/config.js';
import { startCenterServer, type CenterServer } from '../server/app.js';

const require = createRequire(import.meta.url);
const { app, BrowserWindow, ipcMain, shell } = require('electron') as typeof import('electron');

let mainWindow: BrowserWindowType | null = null;
let centerServer: CenterServer | null = null;
const currentDir = dirname(fileURLToPath(import.meta.url));
const appIconPath = join(currentDir, '..', 'renderer', 'images', 'logo.ico');

if (process.platform === 'win32') {
    app.setAppUserModelId('com.alignex.center-server');
}

async function createWindow(): Promise<void> {
    const config = loadConfig();
    centerServer = await startCenterServer({
        port: config.port,
        storagePath: config.storagePath,
        centerId: config.centerId,
        syncBaseUrl: config.syncBaseUrl,
        syncToken: config.syncToken,
        syncAdminEmail: config.syncAdminEmail,
        syncAdminPassword: config.syncAdminPassword,
    });

    mainWindow = new BrowserWindow({
        width: 1360,
        height: 860,
        minWidth: 1120,
        minHeight: 720,
        title: 'AlignEx Center Server',
        icon: appIconPath,
        backgroundColor: '#F8FAFC',
        webPreferences: {
            contextIsolation: true,
            nodeIntegration: false,
            preload: join(currentDir, 'preload.js'),
        },
    });

    const devServerUrl = process.env.VITE_DEV_SERVER_URL;

    if (devServerUrl) {
        await waitForDevServer(devServerUrl);
        await mainWindow.loadURL(devServerUrl);
    } else {
        await mainWindow.loadFile(join(currentDir, '..', 'renderer', 'index.html'));
    }
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

app.whenReady().then(() => {
    registerIpcHandlers();
    void createWindow();
});

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
        app.quit();
    }
});

app.on('before-quit', async (event) => {
    if (!centerServer) {
        return;
    }

    event.preventDefault();
    const server = centerServer;
    centerServer = null;
    await server.stop();
    app.quit();
});

app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
        void createWindow();
    }
});

function registerIpcHandlers(): void {
    ipcMain.handle('alignex:open-export-folder', async (_event, folderPath: unknown) => {
        if (typeof folderPath !== 'string' || folderPath.trim().length === 0) {
            return { success: false, message: 'Export folder path is required.' };
        }

        if (!existsSync(folderPath)) {
            return { success: false, message: 'Export folder does not exist.' };
        }

        const errorMessage = await shell.openPath(folderPath);

        return errorMessage ? { success: false, message: errorMessage } : { success: true };
    });
}
