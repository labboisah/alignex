import { contextBridge, ipcRenderer } from 'electron';
contextBridge.exposeInMainWorld('alignexCandidate', {
    appName: 'AlignEx CBT Candidate Client',
    enterExamMode: () => ipcRenderer.invoke('exam-mode:enter'),
    exitExamMode: () => ipcRenderer.invoke('exam-mode:exit'),
    getSafeDeviceInfo: () => ipcRenderer.invoke('device:get-safe-info'),
    getDeviceFingerprint: () => ipcRenderer.invoke('device:get-fingerprint'),
    onSecurityEvent: (callback) => {
        const listener = (_event, payload) => callback(payload);
        ipcRenderer.on('security:event', listener);
        return () => {
            ipcRenderer.removeListener('security:event', listener);
        };
    },
});
