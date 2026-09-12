import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { buildSync } from 'esbuild';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';

const require = createRequire(import.meta.url);
const compiled = buildSync({ entryPoints: ['resources/js/Pages/ExamMonitor/Evidence.tsx'], bundle: true, platform: 'node', format: 'cjs', write: false, external: ['react'] });
const module = { exports: {} };
new Function('require', 'module', 'exports', compiled.outputFiles[0].text)(require, module, module.exports);
const { EvidenceDetails, EvidenceImage } = module.exports;

test('details render readable labels and values instead of raw JSON', () => {
    const html = renderToStaticMarkup(React.createElement(EvidenceDetails, { payload: { camera_active: true, screen: { width: 640, height: 480 } } }));
    assert.match(html, /Camera active/);
    assert.match(html, /Yes/);
    assert.match(html, /Width: 640; Height: 480/);
    assert.doesNotMatch(html, /&quot;camera_active&quot;/);
});

test('details omit raw images and storage metadata', () => {
    const html = renderToStaticMarkup(React.createElement(EvidenceDetails, { payload: { webcam_snapshot: 'PRIVATE_IMAGE', snapshot_path: 'PRIVATE_PATH', snapshot_url: 'OLD_URL' } }));
    assert.match(html, /No additional details/);
    assert.doesNotMatch(html, /PRIVATE|OLD_URL/);
});

test('evidence provides an image preview and full-image link', () => {
    const url = '/exams/exam/monitor/events/event/evidence';
    const html = renderToStaticMarkup(React.createElement(EvidenceImage, { url }));
    assert.ok(html.includes('src="' + url + '"'));
    assert.ok(html.includes('href="' + url + '"'));
    assert.match(html, /View image/);
    assert.match(renderToStaticMarkup(React.createElement(EvidenceImage, {})), /No image captured/);
});
