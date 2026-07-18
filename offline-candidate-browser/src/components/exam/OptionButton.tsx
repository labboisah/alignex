import { CheckCircle2 } from 'lucide-react';
import type { ExamOption } from '../../types/api';
import { cn } from '../../utils/cn';

export function OptionButton({ option, selected, disabled = false, onSelect }: { option: ExamOption; selected: boolean; disabled?: boolean; onSelect: () => void }) {
    return (
        <button
            className={cn(
                'flex w-full items-start gap-3 rounded-md border bg-white p-3 text-left text-base font-semibold leading-6 text-slateDark transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-info focus-visible:ring-offset-2',
                selected ? 'border-primary bg-primary/5 ring-2 ring-primary/20' : 'border-border hover:border-primary/50 hover:bg-lightBackground',
                disabled && 'cursor-not-allowed opacity-70 hover:border-border hover:bg-white',
            )}
            disabled={disabled}
            onClick={onSelect}
            type="button"
        >
            <span
                className={cn(
                    'flex h-8 w-8 shrink-0 items-center justify-center rounded-md border text-base font-bold',
                    selected ? 'border-primary bg-primary text-white' : 'border-border bg-lightBackground text-slate-600',
                )}
            >
                {option.option_label}
            </span>
            <span className="min-w-0 flex-1">{option.body}</span>
            {selected && <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-primary" />}
        </button>
    );
}
