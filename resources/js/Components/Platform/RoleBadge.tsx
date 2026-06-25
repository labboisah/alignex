import { ShieldCheck } from 'lucide-react';
import { cn } from '@/lib/cn';

export function RoleBadge({ role, className }: { role: string; className?: string }) {
    return (
        <span className={cn('inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slateDark', className)}>
            <ShieldCheck className="h-3.5 w-3.5 text-primary" />
            {role}
        </span>
    );
}
