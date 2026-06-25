import { ReactNode } from 'react';
import { EmptyState } from './EmptyState';
import { LoadingState } from './LoadingState';
import { cn } from '@/lib/cn';

export type DataTableColumn<T> = {
    key: keyof T | string;
    header: string;
    render?: (row: T) => ReactNode;
    className?: string;
};

export function DataTable<T extends Record<string, unknown>>({ columns, rows, loading = false, emptyTitle = 'No records found', className }: { columns: DataTableColumn<T>[]; rows: T[]; loading?: boolean; emptyTitle?: string; className?: string }) {
    if (loading) {
        return <LoadingState message="Loading records..." />;
    }

    if (!rows.length) {
        return <EmptyState title={emptyTitle} description="When records are available they will appear in this table." />;
    }

    return (
        <div className={cn('overflow-hidden rounded-md border border-border bg-white shadow-sm', className)}>
            <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-border bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            {columns.map((column) => (
                                <th key={String(column.key)} className={cn('px-4 py-3 font-semibold', column.className)}>
                                    {column.header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {rows.map((row, rowIndex) => (
                            <tr key={String(row.id ?? rowIndex)} className="hover:bg-slate-50">
                                {columns.map((column) => (
                                    <td key={String(column.key)} className={cn('px-4 py-3 text-slate-700', column.className)}>
                                        {column.render ? column.render(row) : String(row[column.key] ?? '')}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
