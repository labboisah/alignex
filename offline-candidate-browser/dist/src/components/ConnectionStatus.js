import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { CheckCircle2, CircleDashed, WifiOff } from 'lucide-react';
import { cn } from '../utils/cn';
export function ConnectionStatus({ status, label }) {
    const Icon = status === 'connected' ? CheckCircle2 : status === 'disconnected' ? WifiOff : CircleDashed;
    return (_jsxs("div", { className: cn('inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-semibold', status === 'connected' && 'border-success/30 bg-success/5 text-success', status === 'disconnected' && 'border-danger/30 bg-danger/5 text-danger', status === 'idle' && 'border-border bg-lightBackground text-slate-500'), children: [_jsx(Icon, { className: "h-4 w-4" }), label] }));
}
