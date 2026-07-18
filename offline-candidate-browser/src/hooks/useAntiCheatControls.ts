import { useCallback, useEffect, useRef, useState } from 'react';
import { apiClient } from '../services/apiClient';

export type AntiCheatEventType =
    | 'window_blur'
    | 'fullscreen_exit'
    | 'right_click_attempt'
    | 'copy_attempt'
    | 'paste_attempt'
    | 'app_minimized'
    | 'devtools_attempt';

const warningMessage = 'Suspicious action detected. This activity has been recorded.';

export function useAntiCheatControls({ enabled }: { enabled: boolean }) {
    const [warningCount, setWarningCount] = useState(0);
    const [warningVisible, setWarningVisible] = useState(false);
    const lastEventAt = useRef<Record<string, number>>({});

    const recordEvent = useCallback(
        (eventType: AntiCheatEventType) => {
            if (!enabled) {
                return;
            }

            const now = Date.now();
            const lastAt = lastEventAt.current[eventType] ?? 0;

            if (now - lastAt < 1_000) {
                return;
            }

            lastEventAt.current[eventType] = now;
            setWarningCount((count) => count + 1);
            setWarningVisible(true);

            void apiClient.sendCandidateEvent({
                event_type: eventType,
                severity: 'warning',
                message: warningMessage,
                metadata: {
                    client_recorded_at: new Date().toISOString(),
                },
            });
        },
        [enabled],
    );

    useEffect(() => {
        if (!enabled) {
            setWarningVisible(false);
            return;
        }

        function preventContextMenu(event: MouseEvent) {
            event.preventDefault();
            recordEvent('right_click_attempt');
        }

        function preventClipboard(event: ClipboardEvent) {
            event.preventDefault();

            if (event.type === 'copy' || event.type === 'cut') {
                recordEvent('copy_attempt');
                return;
            }

            recordEvent('paste_attempt');
        }

        function handleBlur() {
            recordEvent('window_blur');
        }

        function handleFullscreenChange() {
            if (!document.fullscreenElement) {
                recordEvent('fullscreen_exit');
            }
        }

        function handleVisibilityChange() {
            if (document.visibilityState === 'hidden') {
                recordEvent('window_blur');
            }
        }

        document.addEventListener('contextmenu', preventContextMenu);
        document.addEventListener('copy', preventClipboard);
        document.addEventListener('cut', preventClipboard);
        document.addEventListener('paste', preventClipboard);
        document.addEventListener('fullscreenchange', handleFullscreenChange);
        document.addEventListener('visibilitychange', handleVisibilityChange);
        window.addEventListener('blur', handleBlur);

        const removeSecurityListener = window.alignexCandidate?.onSecurityEvent?.((payload) => {
            if (isAntiCheatEventType(payload.event_type)) {
                recordEvent(payload.event_type);
            }
        });

        return () => {
            document.removeEventListener('contextmenu', preventContextMenu);
            document.removeEventListener('copy', preventClipboard);
            document.removeEventListener('cut', preventClipboard);
            document.removeEventListener('paste', preventClipboard);
            document.removeEventListener('fullscreenchange', handleFullscreenChange);
            document.removeEventListener('visibilitychange', handleVisibilityChange);
            window.removeEventListener('blur', handleBlur);
            removeSecurityListener?.();
        };
    }, [enabled, recordEvent]);

    return {
        warningCount,
        warningMessage,
        warningVisible,
        dismissWarning: () => setWarningVisible(false),
    };
}

function isAntiCheatEventType(value: string): value is AntiCheatEventType {
    return ['window_blur', 'fullscreen_exit', 'right_click_attempt', 'copy_attempt', 'paste_attempt', 'app_minimized', 'devtools_attempt'].includes(value);
}
