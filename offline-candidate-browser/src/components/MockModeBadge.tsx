import { FlaskConical } from 'lucide-react';
import { isMockApiEnabled } from '../services/apiClient';

export function MockModeBadge() {
    if (!isMockApiEnabled()) {
        return null;
    }

    return (
        <div className="fixed right-4 top-4 z-[80] inline-flex items-center gap-2 rounded-md border border-accentOrange/40 bg-accentOrange/10 px-3 py-2 text-sm font-bold text-accentOrange shadow-sm">
            <FlaskConical className="h-4 w-4" />
            Mock Mode
        </div>
    );
}
