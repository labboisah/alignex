import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { Loader2 } from 'lucide-react';
export function LoadingState({ message = 'Loading...' }) {
    return (_jsxs("div", { className: "flex items-center justify-center gap-3 rounded-md border border-border bg-white p-5 text-sm font-semibold text-slate-600", children: [_jsx(Loader2, { className: "h-5 w-5 animate-spin text-info" }), message] }));
}
