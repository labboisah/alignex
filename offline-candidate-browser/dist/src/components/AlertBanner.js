import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { CheckCircle2, Info, TriangleAlert } from 'lucide-react';
import { cn } from '../utils/cn';
export function AlertBanner({ tone = 'info', title, message }) {
    const Icon = tone === 'success' ? CheckCircle2 : tone === 'info' ? Info : TriangleAlert;
    return (_jsx("div", { className: cn('rounded-md border p-4 text-left', tone === 'info' && 'border-info/30 bg-info/5', tone === 'success' && 'border-success/30 bg-success/5', tone === 'warning' && 'border-accentOrange/40 bg-accentOrange/10', tone === 'danger' && 'border-danger/30 bg-danger/5'), children: _jsxs("div", { className: "flex items-start gap-3", children: [_jsx(Icon, { className: cn('mt-0.5 h-5 w-5', tone === 'info' && 'text-info', tone === 'success' && 'text-success', tone === 'warning' && 'text-accentOrange', tone === 'danger' && 'text-danger') }), _jsxs("div", { children: [_jsx("h2", { className: "font-semibold text-slateDark", children: title }), _jsx("p", { className: "mt-1 text-sm leading-6 text-slate-600", children: message })] })] }) }));
}
