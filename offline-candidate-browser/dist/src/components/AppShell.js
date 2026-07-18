import { jsx as _jsx } from "react/jsx-runtime";
export function AppShell({ children }) {
    return (_jsx("main", { className: "flex min-h-screen items-center justify-center bg-lightBackground px-6 py-10", children: _jsx("div", { className: "w-full", children: children }) }));
}
