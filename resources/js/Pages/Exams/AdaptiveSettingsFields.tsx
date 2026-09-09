import { ExamSettings } from './types';

type Props = { settings: ExamSettings; onChange: (value: ExamSettings) => void; questions: number; closesAt: string; duration: string };
const input = 'mt-1 block w-full rounded-md border-border text-sm';

export function AdaptiveSettingsFields({ settings, onChange, questions, closesAt, duration }: Props) {
    const enabled = settings.progressive_remediation_enabled ?? true;
    const number = (key: keyof ExamSettings, label: string, fallback: number, min: number, max: number, step = '1') => <label className="text-sm font-semibold">{label}
        <input className={input} type="number" min={min} max={max} step={step} value={String(settings[key] ?? fallback)} onChange={e=>onChange({...settings,[key]:e.target.value})}/>
    </label>;
    const percent = Math.min(100, Math.max(0, Number(settings.recovery_penalty_percent ?? 10) || 0));
    const exampleCount = 21;
    const exampleMarks = Math.round(200 * (1 - percent / 100)) / 100;
    return <div className="col-span-full space-y-4 rounded border p-4">
        <h3 className="font-semibold">Adaptive learning</h3>
        <p className="text-sm">Level 1 covers the subjects you selected. Further levels use new questions from areas the candidate needs to improve. Question selection and preparation happen automatically when you save.</p>
        <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={enabled} onChange={e=>onChange({...settings,progressive_remediation_enabled:e.target.checked,adaptive_min_questions:questions,adaptive_max_questions:questions})}/> Allow candidates to improve through additional levels</label>
        {enabled && <>
            <div className="grid gap-4 md:grid-cols-2">
                {number('max_scored_levels','Maximum levels (including Level 1)',3,1,20)}
                {number('recovery_penalty_percent','Reduction in marks per question for each new level (%)',10,0,100,'0.01')}
                {number('mastery_threshold_percent','Score needed to finish an area (%)',70,1,100,'0.01')}
                {number('level_duration_minutes','Time for each level (minutes)',Number(duration)||30,1,10080)}
                {number('level_cooldown_minutes','Wait between levels (minutes)',0,0,10080)}
                <label className="text-sm font-semibold">Last date and time for further levels<input className={input} type="datetime-local" value={settings.progression_closes_at ?? closesAt} onChange={e=>onChange({...settings,progression_closes_at:e.target.value})}/></label>
            </div>
            <p className="text-sm">For example, 21 unresolved questions at 2 marks each become {exampleCount} questions at {exampleMarks.toFixed(2)} marks each with a {percent}% reduction ({(exampleCount * exampleMarks).toFixed(2)} marks in total). The question count equals incorrect plus unanswered questions; the percentage only reduces marks per question. Previously earned marks are kept.</p>
            <p className="text-sm">Add enough approved questions for every level. We will tell you what is missing before the exam can open.</p>
        </>}
        <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={settings.adaptive_show_level_feedback ?? true} onChange={e=>onChange({...settings,adaptive_show_level_feedback:e.target.checked})}/> Show candidates their level marks, strengths and areas to improve after each level</label>
    </div>;
}
