'use strict';
const engine = require('./kernel.cjs');
let input = '';
process.stdin.on('data', data => { input += data; if (input.length > 15000000) process.exit(2); });
process.stdin.on('end', () => {
    try {
        const { config, candidate, commands } = JSON.parse(input);
        if (!Array.isArray(commands) || commands.length > 50000) throw new Error('Invalid transcript.');
        let state = engine.initial(config, candidate);
        for (const command of commands) state = engine.transition(config, state, command);
        process.stdout.write(JSON.stringify(engine.summary(state)));
    } catch { process.stderr.write('Pilot replay rejected.'); process.exitCode = 1; }
});
