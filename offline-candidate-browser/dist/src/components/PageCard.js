import { jsx as _jsx } from "react/jsx-runtime";
import { cn } from '../utils/cn';
export function PageCard({ children, className }) {
    return (_jsx("section", { className: cn('mx-auto rounded-md border border-border bg-white p-8 shadow-sm', className), children: children }));
}
