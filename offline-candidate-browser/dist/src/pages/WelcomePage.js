import { jsx as _jsx, jsxs as _jsxs } from "react/jsx-runtime";
import { ArrowRight, ShieldCheck } from 'lucide-react';
import { AlertBanner } from '../components/AlertBanner';
import { AppLogo } from '../components/AppLogo';
import { AppShell } from '../components/AppShell';
import { ConnectionStatus } from '../components/ConnectionStatus';
import { PageCard } from '../components/PageCard';
import { Button } from '../components/ui/button';
export function WelcomePage() {
    return (_jsx(AppShell, { children: _jsxs(PageCard, { className: "max-w-xl text-center", children: [_jsx("div", { className: "flex justify-center", children: _jsx(AppLogo, {}) }), _jsxs("div", { className: "mt-8 space-y-3", children: [_jsxs("div", { className: "inline-flex items-center gap-2 rounded-md border border-primary/20 bg-primary/5 px-3 py-1 text-sm font-semibold text-primary", children: [_jsx(ShieldCheck, { className: "h-4 w-4" }), "CBT Candidate Client"] }), _jsx("h1", { className: "text-3xl font-bold tracking-normal text-slateDark", children: "AlignEx" }), _jsx("p", { className: "text-lg font-semibold text-slate-600", children: "Secure CBT Examination Client" })] }), _jsx("div", { className: "mt-8", children: _jsx(AlertBanner, { tone: "info", title: "Ready for setup", message: "Connect this client to an offline center server before candidate login and exam delivery." }) }), _jsx("div", { className: "mt-8 flex justify-center", children: _jsxs(Button, { size: "lg", children: ["Start Setup", _jsx(ArrowRight, { className: "h-5 w-5" })] }) }), _jsx("div", { className: "mt-8 flex justify-center", children: _jsx(ConnectionStatus, { status: "idle", label: "Not connected to center server" }) })] }) }));
}
