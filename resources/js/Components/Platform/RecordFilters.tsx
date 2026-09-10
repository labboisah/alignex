import { useState } from 'react';
import { Button } from '@/Components/ui/button';

const fields = [['programme', 'Programme'], ['course', 'Course'], ['module', 'Module'], ['subject', 'Subject'], ['question_bank', 'Question bank'], ['status', 'Status'], ['difficulty', 'Difficulty']] as const;
function entry(row: Record<string, any>, key: string) {
    const label = row[key + '_name'] ?? row[key]?.name ?? row['question_bank_' + key + '_name'];
    const value = row[key + '_id'] ?? row[key]?.id ?? label ?? (['status', 'difficulty'].includes(key) ? row[key] : '');
    return { value: String(value ?? ''), label: String(label ?? value ?? '') };
}
export function useRecordFilters<T extends Record<string, any>>(rows: T[], enabled = true) {
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState<Record<string, string>>({});
    if (!enabled) return { filteredRows: rows, controls: null };
    const matches = (row: T, keys = fields.map(([key]) => key as string)) => keys.every(key => !selected[key] || entry(row, key).value === selected[key]);
    const filteredRows = rows.filter(row => matches(row) && [row.name, row.code, row.stem, row.description, ...fields.map(([key]) => entry(row, key).label)].filter(Boolean).join(' ').toLowerCase().includes(search.trim().toLowerCase()));
    const controls = <section aria-label="Filter records" className="mb-4 space-y-3 rounded border border-border bg-white p-4">
        <div className="flex flex-wrap items-end gap-3">
            <label className="text-sm font-semibold">Search records<input aria-label="Search records" type="search" className="mt-1 block rounded border-border" value={search} onChange={event => setSearch(event.target.value)} placeholder="Name, code or question text" /></label>
            {fields.map(([key, label], index) => {
                const options = Array.from(new Map(rows.filter(row => matches(row, fields.slice(0, index).map(([k]) => k))).map(row => { const option = entry(row, key); return [option.value, option] as const; })).values()).filter(option => option.value).sort((a,b) => a.label.localeCompare(b.label));
                if (!rows.some(row => entry(row, key).value)) return null;
                return <label key={key} className="text-sm font-semibold">{label}<select aria-label={'Filter ' + label.toLowerCase()} className="mt-1 block rounded border-border" value={selected[key] ?? ''} onChange={event => {
                    setSelected(previous => ({ ...Object.fromEntries(fields.slice(0,index).map(([k]) => [k,previous[k] ?? ''])), [key]: event.target.value }));
                }}><option value="">All</option>{options.map(option => <option key={option.value} value={option.value}>{option.label}</option>)}</select></label>;
            })}
            <Button type="button" variant="secondary" onClick={() => {setSearch(''); setSelected({});}}>Clear filters</Button>
        </div>
        <p role="status" className="text-sm text-slate-600">Showing {filteredRows.length} of {rows.length} records</p>
    </section>;
    return { filteredRows, controls };
}
