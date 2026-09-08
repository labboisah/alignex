'use strict';
const { createHash } = require('node:crypto');
const VERSION = 'diagnostic-pilot-v1';
const digest = x => createHash('sha256').update(JSON.stringify(x)).digest('hex');
const clone = x => JSON.parse(JSON.stringify(x));
const compare = (a,b) => a < b ? -1 : a > b ? 1 : 0;
const requireThat = (ok, message) => { if (!ok) throw new Error(message); };
const units = x => Math.round(Number(x) * 100);
const sum = xs => xs.reduce((a, b) => a + b, 0);
function validate(c) {
    requireThat(c.version === VERSION && Array.isArray(c.items) && c.items.length <= 10000 && c.items.length > 0, 'Unsupported pilot pool.');
    requireThat(Array.isArray(c.areas) && c.areas.length > 0 && c.areas.length <= 100, 'Invalid areas.');
    requireThat(Number.isSafeInteger(c.starts_at) && Number.isSafeInteger(c.closes_at) && c.closes_at > c.starts_at, 'Invalid window.');
    requireThat(Number.isInteger(c.duration_ms) && c.duration_ms > 0 && c.duration_ms <= 604800000, 'Invalid duration.');
    requireThat(['easy', 'medium', 'hard'].includes(c.start_difficulty), 'Invalid difficulty.');
    requireThat(Number.isInteger(c.max_levels) && c.max_levels >= 1 && c.max_levels <= 20, 'Invalid level limit.');
    requireThat(Number.isInteger(c.penalty_bps) && c.penalty_bps >= 0 && c.penalty_bps <= 10000, 'Invalid penalty.');
    requireThat(Number.isInteger(c.min_budget) && c.min_budget > 0 && Number.isInteger(c.mastery_bps) && c.mastery_bps >= 1 && c.mastery_bps <= 10000, 'Invalid scoring policy.');
    requireThat(Number.isInteger(c.min_evidence) && c.min_evidence > 0 && Number.isInteger(c.cooldown_ms) && c.cooldown_ms >= 0, 'Invalid recovery policy.');
    const keys = new Set(c.areas.map(a => a.key));
    requireThat(keys.size === c.areas.length && new Set(c.items.map(i => i.id)).size === c.items.length, 'Duplicate pool identity.');
    for (const a of c.areas) {
        requireThat(typeof a.key === 'string' && Number.isSafeInteger(a.budget) && a.budget > 0 && a.budget <= 100000000
            && Number.isInteger(a.count) && a.count > 0 && a.count <= 1000 && Array.isArray(a.topics) && a.topics.length <= a.count, 'Invalid area policy.');
    }
    requireThat(sum(c.areas.map(a => a.count)) <= 1000 && sum(c.areas.map(a => a.budget)) <= 100000000, 'Pilot size exceeded.');
    for (const i of c.items) {
        requireThat(typeof i.id === 'string' && keys.has(i.area) && ['easy', 'medium', 'hard'].includes(i.difficulty)
            && ['single_choice', 'multiple_choice', 'true_false'].includes(i.type) && typeof i.stem === 'string'
            && Array.isArray(i.options) && i.options.length >= 2, 'Invalid item.');
        requireThat(new Set(i.options.map(o => o.id)).size === i.options.length
            && i.options.every(o => typeof o.id === 'string' && typeof o.text === 'string' && typeof o.correct === 'boolean')
            && i.options.some(o => o.correct) && (i.type === 'multiple_choice' || i.options.filter(o => o.correct).length === 1), 'Invalid item options.');
    }
    return c;
}
function initial(c, candidate) {
    validate(c);
    return { version: VERSION, candidate, status: 'prepared', state_version: 0, last_at: c.starts_at,
        used: [], levels: [], current: null, receipts: {}, incidents: {}, closed_reason: null,
        balances: c.areas.map(a => ({ key: a.key, original: a.budget, earned: 0, penalty: 0, remaining: a.budget, closed: 0, mastery: 'untested', evidence: 0 })) };
}
function reconcile(s) {
    for (const a of s.balances) requireThat([a.original,a.earned,a.penalty,a.remaining,a.closed].every(x => Number.isSafeInteger(x) && x >= 0)
        && a.original === a.earned + a.penalty + a.remaining + a.closed, 'Ledger mismatch.');
}
function close(s, reason) {
    for (const a of s.balances) { a.closed += a.remaining; a.remaining = 0; }
    s.status = 'closed'; s.closed_reason = reason; s.current = null;
}
function finish(c, s, at, reason) {
    const l = s.levels.at(-1);
    if (!l || l.status !== 'active') return;
    l.status = 'submitted'; l.finished_at = at; l.reason = reason; s.current = null;
    for (const p of l.plan) {
        const a = s.balances.find(a => a.key === p.key);
        const answers = l.answers.filter(r => r.area === p.key);
        a.evidence += answers.length;
        a.mastery = answers.length < c.min_evidence ? 'insufficient_evidence'
            : answers.filter(r => r.correct).length * 10000 >= c.mastery_bps * p.count ? 'mastered' : 'weak';
        if (a.mastery === 'mastered') { a.closed += a.remaining; a.remaining = 0; }
    }
    s.status = 'between_levels';
    if (['supervisor_end', 'disqualified', 'pool_exhausted'].includes(reason)) close(s, reason);
    else if (at >= c.closes_at) close(s, 'deadline');
    else if (l.number >= c.max_levels) close(s, 'level_cap');
    else if (sum(s.balances.map(a => a.remaining)) < c.min_budget) close(s, 'budget_exhausted');
}
function available(c, s, plan) {
    for (const a of plan) {
        const pool = c.items.filter(i => i.area === a.key && !s.used.includes(i.id));
        requireThat(pool.length >= a.count && a.topics.every(t => pool.some(i => i.topic === t)), 'Not enough fresh questions; no penalty charged.');
    }
}
function issue(c, s, correct, at) {
    const l = s.levels.at(-1);
    const bands = ['easy', 'medium', 'hard'];
    const previous = l.issued.at(-1)?.difficulty ?? c.start_difficulty;
    const target = Math.max(0, Math.min(2, bands.indexOf(previous) + (correct === null ? 0 : correct ? 1 : -1)));
    for (const p of l.plan) {
        const used = l.issued.filter(i => i.area === p.key);
        if (used.length >= p.count) continue;
        const missing = p.topics.filter(t => !used.some(i => i.topic === t));
        const pool = c.items.filter(i => i.area === p.key && !s.used.includes(i.id) && (!missing.length || i.topic === missing[0]));
        pool.sort((a,b) => Math.abs(bands.indexOf(a.difficulty)-target) - Math.abs(bands.indexOf(b.difficulty)-target)
            || compare(digest([s.candidate,l.number,l.issued.length,a.id]),digest([s.candidate,l.number,l.issued.length,b.id])));
        if (!pool.length) { finish(c,s,at,'pool_exhausted'); return; }
        const item = pool[0];
        const weight = Math.floor(p.budget/p.count) + (used.length < p.budget % p.count ? 1 : 0);
        const decision = { id: item.id, area: item.area, topic: item.topic, difficulty: item.difficulty, weight };
        l.issued.push(decision); s.used.push(item.id); s.current = { id: item.id, selected: [] }; return;
    }
    finish(c,s,at,'coverage_complete');
}
function begin(c,s,at) {
    const number = s.levels.length + 1;
    requireThat(number <= c.max_levels && at >= c.starts_at && at < c.closes_at
        && (number > 1 || !c.initial_ends_at || at < c.initial_ends_at), 'Outside pilot window.');
    const previous = s.levels.at(-1);
    requireThat(!previous || at >= previous.finished_at + c.cooldown_ms, 'Recovery cooldown has not ended.');
    let plan = c.areas.map(a => ({...a}));
    let penalty = 0;
    const shares = {};
    if (previous) {
        const weak = s.balances.filter(a => a.mastery !== 'mastered' && a.remaining > 0).sort((a,b) => compare(a.key,b.key));
        const incoming = sum(weak.map(a => a.remaining));
        requireThat(incoming > 0, 'No recoverable areas remain.');
        penalty = Number((BigInt(incoming) * BigInt(c.penalty_bps) + 5000n) / 10000n);
        if (incoming - penalty < c.min_budget) { close(s,'minimum_budget'); return; }
        const remainder = [];
        for (const a of weak) {
            const product = BigInt(penalty) * BigInt(a.remaining);
            shares[a.key] = Number(product / BigInt(incoming));
            remainder.push({ key: a.key, value: Number(product % BigInt(incoming)) });
        }
        remainder.sort((a,b) => b.value-a.value || compare(a.key,b.key));
        let left = penalty - sum(Object.values(shares));
        for (const a of remainder) if (left-- > 0) shares[a.key]++;
        plan = weak.map(a => ({...c.areas.find(p => p.key === a.key), budget: a.remaining - shares[a.key]})).filter(a => a.budget > 0);
    }
    available(c,s,plan);
    for (const a of s.balances) if (shares[a.key]) { a.remaining -= shares[a.key]; a.penalty += shares[a.key]; }
    s.levels.push({ number, status: 'active', started_at: at, due_at: Math.min(at+c.duration_ms,c.closes_at,number===1?(c.initial_ends_at??c.closes_at):c.closes_at),
        plan, penalty, earned: 0, answers: [], issued: [] });
    s.status = 'active'; issue(c,s,null,at);
}
function transition(c, previous, command) {
    const s = clone(previous);
    requireThat(s.version === VERSION && typeof command.key === 'string' && command.key.length >= 8 && command.key.length <= 80, 'Invalid command identity.');
    const fingerprint = digest({op:command.op, item_id:command.item_id ?? null, option_ids:command.option_ids ?? [], state_version:command.state_version ?? null,event_name:command.event_name ?? null});
    if (s.receipts[command.key]) {
        requireThat(s.receipts[command.key] === fingerprint, 'Command key was reused for different data.');
        return s;
    }
    requireThat(s.status !== 'closed', 'The completed progression is immutable.');
    requireThat(Number.isSafeInteger(command.at) && command.at >= s.last_at, 'Local clock moved backwards; supervisor recovery required.');
    requireThat(Object.keys(s.receipts).length < 50000, 'Pilot command limit reached.');
    const at = command.at;
    if (s.status === 'active' && at >= s.levels.at(-1).due_at) finish(c,s,at,'timeout');
    if (s.status === 'between_levels' && at >= c.closes_at) close(s,'deadline');
    switch (command.op) {
        case 'start':
            requireThat(s.status === 'prepared', 'Attempt already started or closed.'); begin(c,s,at); break;
        case 'next-level':
            requireThat(s.status === 'between_levels', 'Recovery is unavailable.'); begin(c,s,at); break;
        case 'draft':
        case 'commit': {
            // Deadline finalization succeeds even if an answer arrived too late.
            if (s.status !== 'active') break;
            requireThat(command.state_version === s.state_version && command.item_id === s.current.id, 'Stale answer; reload the current question.');
            const item = c.items.find(i => i.id === s.current.id);
            requireThat(Array.isArray(command.option_ids) && command.option_ids.length <= item.options.length
                && command.option_ids.every(id => item.options.some(o => o.id === id)), 'Invalid options.');
            const chosen = [...new Set(command.option_ids)].sort();
            requireThat(item.type === 'multiple_choice' || chosen.length <= 1, 'Choose one option.');
            s.current.selected = chosen;
            if (command.op === 'commit') {
                const correct = JSON.stringify(chosen) === JSON.stringify(item.options.filter(o => o.correct).map(o => o.id).sort());
                const l = s.levels.at(-1), d = l.issued.at(-1), earned = correct ? d.weight : 0;
                const a = s.balances.find(a => a.key === d.area);
                a.earned += earned; a.remaining -= earned; l.earned += earned;
                l.answers.push({ item_id: item.id, area: item.area, selected: chosen, correct, earned });
                issue(c,s,correct,at);
            }
            break;
        }
        case 'event':
            requireThat(['tab_blur','focus','fullscreen_exit','copy_attempt','paste_attempt','network_reconnect'].includes(command.event_name),'Unsupported incident.');
            s.incidents[command.event_name] = (s.incidents[command.event_name] ?? 0) + 1;
            if (command.event_name === 'tab_blur' && c.max_tab_switches > 0 && s.incidents.tab_blur > c.max_tab_switches) {
                finish(c,s,at,'disqualified'); close(s,'disqualified');
            }
            break;
        case 'submit': finish(c,s,at,'submitted'); break;
        case 'tick': break;
        case 'supervisor_end':
        case 'disqualified':
            finish(c,s,at,command.op); close(s,command.op); break;
        default: throw new Error('Unsupported command.');
    }
    if (command.op !== 'event' || s.status !== previous.status) s.state_version++;
    s.last_at = at; s.receipts[command.key] = fingerprint; reconcile(s); return s;
}
function view(c,s,now) {
    const item = s.current ? c.items.find(i => i.id === s.current.id) : null;
    return { version: VERSION, status: s.status, state_version: s.state_version, level: s.levels.length || 1,
        current_item: item ? {id:item.id, type:item.type, stem:item.stem, options:item.options.map(o => ({id:o.id,label:o.label,text:o.text}))} : null,
        selected: s.current?.selected ?? [], remaining_seconds: s.status === 'active' ? Math.max(0,Math.ceil((s.levels.at(-1).due_at-now)/1000)) : 0,
        answered: s.levels.at(-1)?.answers.length ?? 0, stop_reason: s.levels.at(-1)?.reason ?? s.closed_reason,
        recovery_at: s.status === 'between_levels' ? s.levels.at(-1).finished_at+c.cooldown_ms : null,
        result_pending_cloud_review: s.status === 'closed' };
}
function summary(s) {
    reconcile(s);
    return { version:VERSION,status:s.status,stop_reason:s.closed_reason,
        balances:s.balances,levels:s.levels.map(l => ({number:l.number,status:l.status,earned:l.earned,penalty:l.penalty,
            available:sum(l.plan.map(p=>p.budget)),answered:l.answers.length,started_at:l.started_at,due_at:l.due_at,finished_at:l.finished_at,stop_reason:l.reason})),
        state_hash:digest(s),consequential_approved:false };
}
module.exports = {VERSION,digest,validate,initial,transition,view,summary};
