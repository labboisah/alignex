import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { transformSync } from 'esbuild';

const source = transformSync(readFileSync(new URL('../../resources/js/Pages/CandidateExam/fullscreen.ts', import.meta.url), 'utf8'), { loader: 'ts', format: 'esm' }).code;
const { enterExamFullscreen, isExamFullscreen, watchExamFullscreen } = await import('data:text/javascript;base64,' + Buffer.from(source).toString('base64'));

function setup() {
    const doc = new EventTarget();
    doc.documentElement = {};
    doc.fullscreenElement = null;
    globalThis.document = doc;
    return doc;
}

test('unsupported phone shows actionable message instead of calling a missing function', async () => {
    setup();
    await assert.rejects(enterExamFullscreen(), /This phone or browser cannot enter fullscreen/);
    assert.equal(isExamFullscreen(), false);
});

test('standard request is called immediately with the element as receiver', async () => {
    const doc = setup();
    let called = false;
    doc.documentElement.requestFullscreen = function () {
        assert.equal(this, doc.documentElement);
        called = true;
        doc.fullscreenElement = this;
        doc.dispatchEvent(new Event('fullscreenchange'));
        return Promise.resolve();
    };
    const pending = enterExamFullscreen();
    assert.equal(called, true);
    await pending;
    assert.equal(isExamFullscreen(), true);
});

test('WebKit request and fullscreen exit use prefixed state and events', async () => {
    const doc = setup();
    doc.documentElement.webkitRequestFullscreen = function () {
        assert.equal(this, doc.documentElement);
        queueMicrotask(() => {
            doc.webkitFullscreenElement = this;
            doc.dispatchEvent(new Event('webkitfullscreenchange'));
        });
    };
    await enterExamFullscreen();
    assert.equal(isExamFullscreen(), true);
    let changes = 0;
    const stop = watchExamFullscreen(() => changes++);
    doc.webkitFullscreenElement = null;
    doc.dispatchEvent(new Event('webkitfullscreenchange'));
    assert.equal(isExamFullscreen(), false);
    assert.equal(changes, 1);
    stop();
    doc.dispatchEvent(new Event('webkitfullscreenchange'));
    assert.equal(changes, 1);
});

test('rejected fullscreen permission remains blocked and supports retry', async () => {
    const doc = setup();
    doc.documentElement.requestFullscreen = () => Promise.reject(new Error('denied'));
    await assert.rejects(enterExamFullscreen(), /Fullscreen was not allowed/);
    assert.equal(isExamFullscreen(), false);
    doc.documentElement.requestFullscreen = function () { doc.fullscreenElement = this; };
    await enterExamFullscreen();
    assert.equal(isExamFullscreen(), true);
});

test('already fullscreen does not request permission again', async () => {
    const doc = setup();
    doc.fullscreenElement = doc.documentElement;
    doc.documentElement.requestFullscreen = () => assert.fail('unnecessary fullscreen request');
    await enterExamFullscreen();
});
