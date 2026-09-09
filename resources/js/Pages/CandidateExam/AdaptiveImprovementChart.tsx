import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

type Progress = {
    original_marks: string;
    levels: { number: number; score: string }[];
};

export default function AdaptiveImprovementChart({ progress }: { progress: Progress }) {
    // Sum the released marks in hundredths to avoid accumulating decimal rounding errors.
    let earnedUnits = 0;
    const points = [...progress.levels].sort((a, b) => a.number - b.number).map(level => {
        earnedUnits += Math.round(Number(level.score) * 100);
        return { level: 'Level ' + level.number, earned: earnedUnits / 100 };
    });
    if (points.length === 0) return null;
    const improvement = points[points.length - 1].earned - points[0].earned;
    const original = Number(progress.original_marks);

    return <section aria-labelledby="improvement-title" className="rounded-lg border border-green-200 bg-green-50/40 p-4">
        <h3 id="improvement-title" className="text-lg font-semibold">Your improvement</h3>
        <p className="mt-1 text-sm text-slate-600">Total marks earned after each completed level, out of {original.toFixed(2)}.</p>
        <p className="mt-2 font-semibold text-primary">
            {points.length === 1 ? 'Level 1 is your starting point. Complete another level to see your improvement.'
                : improvement > 0 ? `+${improvement.toFixed(2)} marks gained since Level 1.`
                    : 'Your total marks have stayed the same since Level 1.'}
        </p>
        <div className="mt-4 h-64 w-full min-w-0" aria-label="Cumulative earned marks by level">
            <ResponsiveContainer width="100%" height="100%">
                <LineChart data={points} margin={{ top: 12, right: 20, bottom: 8, left: 0 }} accessibilityLayer>
                    <CartesianGrid strokeDasharray="3 3" stroke="#E2E8F0" vertical={false} />
                    <XAxis dataKey="level" tick={{ fontSize: 12 }} />
                    <YAxis domain={[0, Math.max(original, 1)]} width={45} tick={{ fontSize: 12 }} />
                    <Tooltip formatter={(value: number) => [value.toFixed(2) + ' marks', 'Total earned']} />
                    <Line type="linear" dataKey="earned" name="Total earned" stroke="#0F7A3A" strokeWidth={3}
                        dot={{ r: 5, fill: '#0F7A3A' }} activeDot={{ r: 7 }} isAnimationActive={false} />
                </LineChart>
            </ResponsiveContainer>
        </div>
        <table className="sr-only">
            <caption>Total marks earned after each level</caption>
            <thead><tr><th scope="col">Level</th><th scope="col">Total earned marks</th></tr></thead>
            <tbody>{points.map(point => <tr key={point.level}><th scope="row">{point.level}</th><td>{point.earned.toFixed(2)}</td></tr>)}</tbody>
        </table>
        <p className="mt-2 text-xs text-slate-600">This shows earned marks accumulating across levels. Each new level can have a different number of available marks.</p>
    </section>;
}
