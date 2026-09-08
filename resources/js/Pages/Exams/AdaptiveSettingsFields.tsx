import { ExamSettings } from './types';

type Props = { settings: ExamSettings; onChange: (value: ExamSettings) => void; questions: number; closesAt: string; duration: string };
const input = 'mt-1 block w-full rounded-md border-border text-sm';

export function AdaptiveSettingsFields({ settings, onChange, questions, closesAt, duration }: Props) {
    const numberField = (key: keyof ExamSettings, label: string, fallback: number, min: number, max: number, step = '1') => (
        <label className="text-sm font-semibold" key={key}>{label}
            <input className={input} type="number" min={min} max={max} step={step} value={String(settings[key] ?? fallback)} onChange={event => onChange({ ...settings, [key]: event.target.value })} />
        </label>
    );
    return (
        <div className="col-span-full space-y-4 rounded-md border border-border p-4">
            <p className="text-sm text-slate-600">Prepare adaptive settings here. Live delivery remains disabled until the adaptive workflow is ready.</p>
            <div className="grid gap-4 md:grid-cols-2">
                <label className="text-sm font-semibold">Starting difficulty
                    <select className={input} value={settings.adaptive_start_difficulty ?? 'medium'} onChange={event => onChange({ ...settings, adaptive_start_difficulty: event.target.value })}>
                        <option value="easy">Easy</option><option value="medium">Medium</option><option value="hard">Hard</option>
                    </select>
                </label>
                <label className="text-sm font-semibold">Selection policy
                    <select className={input} value={settings.adaptive_step_policy ?? 'simple'} onChange={event => onChange({ ...settings, adaptive_step_policy: event.target.value })}>
                        <option value="simple">Adjust difficulty after each committed answer</option>
                    </select>
                </label>
                {numberField('adaptive_min_questions', 'Minimum questions', questions, 1, 1000)}
                {numberField('adaptive_max_questions', 'Maximum questions', questions, 1, 1000)}
            </div>
            <label className="flex items-center gap-2 text-sm font-semibold">
                <input type="checkbox" checked={settings.progressive_remediation_enabled ?? false} onChange={event => onChange({
                    ...settings, progressive_remediation_enabled: event.target.checked,
                    ...(event.target.checked ? {
                        adaptive_min_questions: questions, adaptive_max_questions: questions,
                        recovery_penalty_percent: settings.recovery_penalty_percent ?? 10,
                        max_scored_levels: settings.max_scored_levels ?? 3,
                        min_level_budget: settings.min_level_budget ?? 1,
                        mastery_threshold_percent: settings.mastery_threshold_percent ?? 70,
                        min_evidence_per_area: settings.min_evidence_per_area ?? 1,
                        level_duration_minutes: settings.level_duration_minutes ?? Number(duration || 30),
                        progression_closes_at: settings.progression_closes_at ?? closesAt,
                        level_cooldown_minutes: settings.level_cooldown_minutes ?? 0,
                        allow_unscored_remediation: settings.allow_unscored_remediation ?? false,
                    } : {}),
                })} />
                Enable weakness-focused recovery levels
            </label>
            {settings.progressive_remediation_enabled && <>
                <p className="text-sm text-slate-600">Each additional scored level deducts the configured percentage from remaining recoverable marks. Earned marks are retained. Question limits must equal the total paper-row quota. Negative marking is incompatible.</p>
                <div className="grid gap-4 md:grid-cols-2">
                    {numberField('recovery_penalty_percent', 'Recovery penalty (%)', 10, 0, 100, '0.01')}
                    {numberField('max_scored_levels', 'Maximum scored levels (including Level 1)', 3, 1, 20)}
                    {numberField('min_level_budget', 'Minimum level budget (marks)', 1, 0.01, 1000000, '0.01')}
                    {numberField('mastery_threshold_percent', 'Mastery threshold (%)', 70, 1, 100, '0.01')}
                    {numberField('min_evidence_per_area', 'Minimum questions per area for mastery', 1, 1, 1000)}
                    {numberField('level_duration_minutes', 'Level duration (minutes)', 30, 1, 10080)}
                    {numberField('level_cooldown_minutes', 'Wait between levels (minutes)', 0, 0, 10080)}
                    <label className="text-sm font-semibold">Progression closes at
                        <input className={input} type="datetime-local" value={settings.progression_closes_at ?? ''} onChange={event => onChange({ ...settings, progression_closes_at: event.target.value })} />
                    </label>
                </div>
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={settings.allow_unscored_remediation ?? false} onChange={event => onChange({ ...settings, allow_unscored_remediation: event.target.checked })} />Allow unscored practice after scored progression closes</label>
            </>}
        </div>
    );
}
