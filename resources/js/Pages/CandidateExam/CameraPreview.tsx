import { forwardRef, useState } from 'react';

export const CameraPreview = forwardRef<HTMLVideoElement, { error?: string }>(function CameraPreview({ error }, ref) {
    const [playing, setPlaying] = useState(false);
    return <section aria-label="Your live camera" className="w-48 shrink-0 overflow-hidden rounded-lg border border-border bg-white shadow-sm">
        <div className="flex items-center justify-between px-3 py-2 text-sm">
            <h2 className="font-semibold text-slate-900">Your camera</h2>
            <span role="status" className={playing && !error ? 'text-green-700' : 'text-slate-500'}>{error ? 'Unavailable' : playing ? 'Live' : 'Waiting'}</span>
        </div>
        <video ref={ref} autoPlay muted playsInline aria-label="Your live webcam preview"
            onPlaying={() => setPlaying(true)} onPause={() => setPlaying(false)} onEnded={() => setPlaying(false)}
            className="aspect-video w-full -scale-x-100 bg-slate-900 object-cover" />
        <p className="px-3 py-2 text-xs text-slate-600">{error || (playing ? 'Keep your face visible in the frame.' : 'Waiting for camera access. Enable the exam controls when prompted.')}</p>
    </section>;
});
