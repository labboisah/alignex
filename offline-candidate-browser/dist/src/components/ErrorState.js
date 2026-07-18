import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { TriangleAlert } from 'lucide-react';
import { Button } from './ui/button';
export function ErrorState({ title = 'Something went wrong', message, onRetry }) {
    return (_jsx("div", { className: "rounded-md border border-danger/30 bg-danger/5 p-5 text-left", children: _jsxs("div", { className: "flex items-start gap-3", children: [_jsx(TriangleAlert, { className: "mt-0.5 h-5 w-5 text-danger" }), _jsxs("div", { className: "min-w-0 flex-1", children: [_jsx("h2", { className: "font-semibold text-slateDark", children: title }), _jsx("p", { className: "mt-1 text-sm leading-6 text-slate-600", children: message }), onRetry && (_jsx(Button, { className: "mt-4", onClick: onRetry, size: "sm", variant: "outline", children: "Try Again" }))] })] }) }));
}
