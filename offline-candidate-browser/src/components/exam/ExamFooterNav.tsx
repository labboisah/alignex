import { ArrowLeft, ArrowRight, Send } from 'lucide-react';
import { Button } from '../ui/button';

export function ExamFooterNav({
    canGoPrevious,
    canGoNext,
    onPrevious,
    onNext,
    onSubmit,
    nextLabel = 'Next',
    submitDisabled = false,
    submitDisabledReason,
}: {
    canGoPrevious: boolean;
    canGoNext: boolean;
    onPrevious: () => void;
    onNext: () => void;
    onSubmit: () => void;
    nextLabel?: string;
    submitDisabled?: boolean;
    submitDisabledReason?: string;
}) {
    return (
        <footer className="sticky bottom-0 z-20 border-t border-border bg-white/95 px-5 py-4 shadow-sm backdrop-blur">
            <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3">
                <Button disabled={!canGoPrevious} onClick={onPrevious} size="lg" type="button" variant="outline">
                    <ArrowLeft className="h-5 w-5" />
                    Previous
                </Button>
                <div className="flex flex-wrap gap-3">
                    <Button disabled={!canGoNext} onClick={onNext} size="lg" type="button" variant="secondary">
                        {nextLabel}
                        <ArrowRight className="h-5 w-5" />
                    </Button>
                    <Button
                        className="border-2 border-danger/20 px-8"
                        disabled={submitDisabled}
                        onClick={onSubmit}
                        size="lg"
                        title={submitDisabledReason}
                        type="button"
                        variant="danger"
                    >
                        <Send className="h-5 w-5" />
                        Submit
                    </Button>
                </div>
            </div>
        </footer>
    );
}
