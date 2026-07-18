import { contextBridge, ipcRenderer } from 'electron';

contextBridge.exposeInMainWorld('alignex', {
    openExportFolder: (folderPath: string) => ipcRenderer.invoke('alignex:open-export-folder', folderPath) as Promise<{ success: boolean; message?: string }>,
});
