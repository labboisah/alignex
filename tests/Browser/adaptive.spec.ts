import { test, expect, type Page, type APIRequestContext } from '@playwright/test';

async function fixture(request: APIRequestContext, input: Record<string, unknown> = {}) {
    const response = await request.post('/__browser/fixture', { data: input });
    expect(response.ok(), (await response.text()).slice(0, 1000)).toBeTruthy();
    return response.json();
}
async function login(page: Page, data: { code: string; identifier: string }) {
    await page.goto('/exam/login');
    await page.getByLabel('Exam Code', { exact: true }).fill(data.code);
    await page.getByLabel('Registration Number, Phone, or NIN').fill(data.identifier);
    await page.getByRole('button', { name: 'Continue', exact: true }).click();
}
async function start(page: Page, request: APIRequestContext, input: Record<string, unknown> = {}) {
    const data = await fixture(request, input);
    await login(page, data);
    await expect(page.getByRole('heading', { name: 'Before you begin' })).toBeVisible();
    await expect(page.getByRole('radio')).toHaveCount(0);
    await page.getByRole('button', { name: 'Start adaptive exam' }).click();
    await expect(page.getByRole('radio')).toHaveCount(2);
    return data;
}
async function answer(page: Page, correct = false) {
    await page.getByRole('radio', { name: correct ? 'A. First option' : 'B. Second option' }).check();
    await page.getByRole('button', { name: 'Confirm and continue' }).click();
    await expect(page.getByRole('button', { name: 'Saving…' })).toHaveCount(0);
}
async function envelope(page: Page) {
    return page.evaluate(() => JSON.parse(localStorage.getItem('alignex_adaptive_session')!));
}
test.beforeEach(async ({ page }) => { page.on('dialog', dialog => dialog.accept()); });

for (const owner of ['organization', 'institution', 'professional_school', 'cbt_center']) {
    test(owner + ': explicit start, current-only answers and recovery', async ({ page, request }) => {
        const data = await start(page, request, { owner });
        const first = await envelope(page);
        expect(first.payload.questions).toBeUndefined();
        expect(first.payload.current_item.options[0].is_correct).toBeUndefined();
        await expect(page.getByRole('button', { name: 'Previous', exact: true })).toHaveCount(0);
        await answer(page, true);
        expect((await envelope(page)).payload.current_item.question_id).not.toBe(first.payload.current_item.question_id);
        await answer(page);
        await answer(page);
        await expect(page.getByRole('heading', { name: 'Level completed' })).toBeVisible();
        await expect(page.getByText('Scores remain hidden', { exact: false })).toBeVisible();
        await page.getByRole('button', { name: 'Start recovery level' }).click();
        await expect(page.getByRole('radio')).toHaveCount(2);
        expect((await envelope(page)).payload.level).toBe(2);
        const state = await (await request.get('/__browser/state/' + data.exam_id)).json();
        expect(state.levels).toBe(2);
        expect(Number(state.penalties)).toBe(40);
        expect(state.rows).toHaveLength(1);
        expect(state.rows[0].adaptive.history).toHaveLength(2);
    });
}

test('draft and keyboard selection survive refresh; duplicate clicks commit once', async ({ page, request }) => {
    const data = await start(page, request);
    const initial = await envelope(page);
    await page.getByRole('radio').first().focus();
    await page.keyboard.press('Space');
    await page.getByRole('button', { name: 'Save draft' }).click();
    await expect(page.getByText('Draft saved.', { exact: true })).toBeVisible();
    await page.reload();
    await expect(page.getByRole('radio').first()).toBeChecked();
    expect((await envelope(page)).payload.current_item.question_id).toBe(initial.payload.current_item.question_id);
    await page.getByRole('button', { name: 'Confirm and continue' }).evaluate(button => { (button as HTMLButtonElement).click(); (button as HTMLButtonElement).click(); });
    await expect(page.getByText('1 responses confirmed', { exact: true })).toBeVisible();
    const state = await (await request.get('/__browser/state/' + data.exam_id)).json();
    expect(state.committed).toBe(1);
    await page.reload();
    await expect(page.getByText('1 responses confirmed', { exact: true })).toBeVisible();
});

test('lost commit response and offline reconnect reuse the durable operation', async ({ page, request, context }) => {
    const data = await start(page, request);
    await page.route('**/api/candidate/answer', async route => { await route.fetch(); await route.abort('failed'); });
    await answer(page, true);
    await expect(page.getByRole('button', { name: 'Retry pending request' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Retry pending request' })).toBeEnabled();
    const pending = (await envelope(page)).pending;
    expect(pending.body.idempotency_key).toBeTruthy();
    await page.unroute('**/api/candidate/answer');
    await page.reload();
    await expect(page.getByText('1 responses confirmed', { exact: true })).toBeVisible();
    expect((await envelope(page)).pending.body.idempotency_key).toBe(pending.body.idempotency_key);
    await context.setOffline(true);
    await expect(page.getByText('You are offline.', { exact: false })).toBeVisible();
    await context.setOffline(false);
    await page.getByRole('button', { name: 'Retry pending request' }).click();
    await expect(page.getByRole('button', { name: 'Retry pending request' })).toHaveCount(0);
    const state = await (await request.get('/__browser/state/' + data.exam_id)).json();
    expect(state.committed).toBe(1);
    expect(Number(state.earned)).toBe(200);
});

test('lost recovery response restores the new token without a second penalty', async ({ page, request }) => {
    const data = await start(page, request);
    await page.getByRole('button', { name: 'Finish level', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Level completed' })).toBeVisible();
    const oldToken = (await envelope(page)).token;
    await page.route('**/api/candidate/next-level', async route => { await route.fetch(); await route.abort('failed'); });
    await page.getByRole('button', { name: 'Start recovery level' }).click();
    await expect(page.getByRole('button', { name: 'Retry pending request' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Retry pending request' })).toBeEnabled();
    const key = (await envelope(page)).pending.body.idempotency_key;
    await page.unroute('**/api/candidate/next-level');
    await page.reload();
    await expect(page.getByRole('button', { name: 'Retry pending request' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Retry pending request' })).toBeEnabled();
    expect((await envelope(page)).pending.body.idempotency_key).toBe(key);
    await page.getByRole('button', { name: 'Retry pending request' }).click();
    await expect(page.getByRole('radio')).toHaveCount(2);
    const next = await envelope(page);
    expect(next.token).not.toBe(oldToken);
    expect(next.payload.level).toBe(2);
    const state = await (await request.get('/__browser/state/' + data.exam_id)).json();
    expect(state.levels).toBe(2);
    expect(Number(state.penalties)).toBe(60);
    await page.reload();
    await expect(page.getByRole('radio')).toHaveCount(2);
    expect((await envelope(page)).payload.level).toBe(2);
});

test('another tab must refresh before it can answer the advanced question', async ({ page, request, context }) => {
    await start(page, request);
    const second = await context.newPage();
    await second.goto('/exam/write');
    await expect(second.getByRole('radio')).toHaveCount(2);
    await answer(page);
    await expect(second.getByText('This exam changed in another tab.', { exact: false })).toBeVisible();
    await expect(second.getByRole('button', { name: 'Confirm and continue' })).toBeDisabled();
    await second.getByRole('button', { name: 'Refresh exam state' }).click();
    await expect(second.getByRole('button', { name: 'Confirm and continue' })).toBeEnabled();
    expect((await envelope(second)).payload.current_item.question_id).toBe((await envelope(page)).payload.current_item.question_id);
    await second.close();
});

test('server expiry finalizes the level and supervisor ending closes recovery', async ({ page, request }) => {
    const data = await start(page, request);
    await answer(page, true);
    await request.post('/__browser/action', { data: { ...data, action: 'expire' } });
    await page.getByRole('button', { name: 'Refresh exam state' }).click();
    await expect(page.getByText('Time expired.', { exact: false })).toBeVisible();
    await expect(page.getByRole('radio')).toHaveCount(0);
    await request.post('/__browser/action', { data: { ...data, action: 'end' } });
    await page.getByRole('button', { name: 'Refresh exam state' }).click();
    await expect(page.getByRole('button', { name: 'Start recovery level' })).toHaveCount(0);
});

test('proctor tab policy disqualifies through browser events and prevents further answers', async ({ page, request }) => {
    await start(page, request, { settings: { max_tab_switches: 1 } });
    await page.evaluate(() => window.dispatchEvent(new Event('blur')));
    await page.waitForResponse(response => response.url().endsWith('/api/candidate/event'));
    const eventResponse = page.waitForResponse(response => response.url().endsWith('/api/candidate/event') && response.request().postDataJSON().event_type === 'window_blur');
    await page.evaluate(() => window.dispatchEvent(new Event('blur')));
    await eventResponse;
    await expect(page.getByRole('heading', { name: 'Exam disqualified' })).toBeVisible();
    await expect(page.getByRole('radio')).toHaveCount(0);
    await page.reload();
    await expect(page.getByRole('heading', { name: 'Exam disqualified' })).toBeVisible();
});

test('camera denial blocks start; camera and fullscreen controls can be restored', async ({ page, request, context }) => {
    const data = await fixture(request, { settings: { require_webcam: true, require_fullscreen: true } });
    await context.grantPermissions(['camera']);
    await page.addInitScript(() => {
        const original = navigator.mediaDevices.getUserMedia.bind(navigator.mediaDevices);
        let calls = 0;
        navigator.mediaDevices.getUserMedia = constraints => ++calls === 1 ? Promise.reject(new Error('Camera permission denied for test.')) : original(constraints);
    });
    await login(page, data);
    await expect(page.getByRole('button', { name: 'Start adaptive exam' })).toBeDisabled();
    await page.getByRole('button', { name: 'Enable required exam controls' }).click();
    await expect(page.getByText('Camera permission denied for test.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Start adaptive exam' })).toBeDisabled();
    await page.getByRole('button', { name: 'Enable required exam controls' }).click();
    await page.getByRole('button', { name: 'Start adaptive exam' }).click();
    await expect(page.getByRole('radio')).toHaveCount(2);
    // Routing can release the initial camera stream; the writing view offers explicit restoration.
    if (await page.getByRole('button', { name: 'Restore exam controls' }).isVisible()) await page.getByRole('button', { name: 'Restore exam controls' }).click();
    await expect(page.getByRole('radio').first()).toBeEnabled();
    await page.evaluate(() => document.exitFullscreen());
    await expect(page.getByRole('button', { name: 'Confirm and continue' })).toBeDisabled();
    await page.getByRole('button', { name: 'Restore exam controls' }).click();
    await expect(page.getByRole('button', { name: 'Confirm and continue' })).toBeEnabled();
});

for (const owner of ['organization', 'secondary_school']) {
    test(owner + ': traditional paper navigation and flags remain available', async ({ page, request }) => {
        const data = await fixture(request, { owner, traditional: true });
        await login(page, data);
        await page.getByRole('button', { name: 'Start Exam', exact: true }).click();
        await expect(page.getByRole('button', { name: 'Flag', exact: true })).toBeVisible();
        const first = await page.getByText(/Browser question/).first().textContent();
        await page.getByRole('button', { name: 'Flag', exact: true }).click();
        await page.getByRole('button', { name: 'Next', exact: true }).click();
        await expect(page.getByText(first!, { exact: true })).toHaveCount(0);
        await page.getByRole('button', { name: 'Previous', exact: true }).click();
        await expect(page.getByText(first!, { exact: true })).toBeVisible();
        const stored = await page.evaluate(() => JSON.parse(localStorage.getItem('alignex_exam_payload')!));
        expect(stored.questions.data?.length ?? stored.questions.length).toBe(3);
        expect(await page.evaluate(() => localStorage.getItem('alignex_adaptive_session'))).toBeNull();
    });
}

test('completed aggregate is displayed only when the release policy permits it', async ({ page, request }) => {
    await start(page, request, { settings: { max_scored_levels: 1, show_result_immediately: true } });
    await answer(page, true);
    await page.getByRole('button', { name: 'Finish level', exact: true }).click();
    await expect(page.getByText('Released aggregate result: 2.00 / 6.00')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Start recovery level' })).toHaveCount(0);
});

test('authorized supervisor sees level history, immutable reset and stop events', async ({ page, request, context }) => {
    const data = await start(page, request);
    await page.getByRole('button', { name: 'Finish level', exact: true }).click();
    await page.getByRole('button', { name: 'Start recovery level' }).click();
    await expect(page.getByRole('radio')).toHaveCount(2);
    const supervisor = await context.newPage();
    supervisor.on('dialog', dialog => dialog.accept());
    await supervisor.goto('/login');
    await supervisor.getByLabel('Email', { exact: true }).fill(data.actor_email);
    await supervisor.getByLabel('Password', { exact: true }).fill('password');
    await supervisor.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(supervisor).not.toHaveURL(/\/login$/);
    await supervisor.goto('/exams/' + data.exam_id + '/monitor');
    await supervisor.getByText(/Adaptive level 2/).click();
    await expect(supervisor.getByText(/Level 1: submitted/)).toBeVisible();
    await expect(supervisor.getByText(/Level 2: active/)).toBeVisible();
    await expect(supervisor.getByRole('button', { name: 'History retained' })).toBeDisabled();
    await supervisor.getByRole('button', { name: 'End Exam', exact: true }).click();
    await expect(supervisor.getByText(/adaptive_supervisor_end|Adaptive Supervisor End/).first()).toBeVisible();
    await page.getByRole('button', { name: 'Refresh exam state' }).click();
    await expect(page.getByText('Your supervisor has ended this progression.')).toBeVisible();
    await supervisor.close();
});

test('adaptive diagnostic report shows recovery history and handles export failure and retry', async ({ page, request, context }) => {
    const data = await start(page, request, { report_exports: true });
    await answer(page, true);
    await page.getByRole('button', { name: 'Finish level', exact: true }).click();
    await page.getByRole('button', { name: 'Start recovery level' }).click();
    await expect(page.getByRole('radio')).toHaveCount(2);
    const report = await context.newPage();
    await report.goto('/login');
    await report.getByLabel('Email', { exact: true }).fill(data.actor_email);
    await report.getByLabel('Password', { exact: true }).fill('password');
    await report.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(report).not.toHaveURL(/\/login$/);
    await report.goto('/results/adaptive/exams/' + data.exam_id);
    await expect(report.getByRole('link', { name: 'View progression' })).toHaveCount(1);
    await report.getByRole('link', { name: 'View progression' }).click();
    await expect(report.getByRole('heading', { name: 'Recovered marks', exact: true })).toBeVisible();
    await expect(report.getByText('Withheld', { exact: true })).toBeVisible();
    await expect(report.getByRole('heading', { name: /Level 1/ })).toBeVisible();
    await expect(report.getByRole('heading', { name: /Level 2/ })).toBeVisible();
    await expect(report.getByText(/Do not use for recruitment/)).toBeVisible();
    await report.locator('article').first().locator('summary').click();
    await expect(report.locator('article').first().getByText('Unconfirmed', { exact: true })).toBeVisible();
    await report.route('**/results/adaptive/progressions/*/export.csv', route => route.fulfill({ status: 503, body: 'Unavailable' }));
    await report.getByRole('button', { name: 'Download diagnostic CSV' }).click();
    await expect(report.getByText(/Export could not be downloaded/)).toBeVisible();
    await report.unroute('**/results/adaptive/progressions/*/export.csv');
    const download = report.waitForEvent('download');
    await report.getByRole('button', { name: 'Download diagnostic CSV' }).click();
    expect((await download).suggestedFilename()).toMatch(/adaptive-progression-\d+\.csv/);
    await expect(report.getByText('Report downloaded.', { exact: true })).toBeVisible();
    await report.close();
});

test('adaptive report list explains its empty state before candidate preparation', async ({ page, request }) => {
    const data = await fixture(request);
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(data.actor_email);
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).not.toHaveURL(/\/login$/);
    await page.goto('/results/adaptive/exams/' + data.exam_id);
    await expect(page.getByText('No adaptive candidate progressions have been prepared.')).toBeVisible();
});
