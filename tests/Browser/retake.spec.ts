import { test, expect } from '@playwright/test';

test('admin schedules and cancels a candidate retake without losing the current result', async ({ page, request }) => {
    const fixture = await request.post('/__browser/fixture', { data: { traditional: true, management: true } });
    expect(fixture.ok()).toBeTruthy();
    const data = await fixture.json();
    const login = await request.post('/api/candidate/login', { data: {
        exam_code: data.code, registration_number: data.identifier, device_fingerprint: 'retake-browser',
    } });
    expect(login.ok(), await login.text()).toBeTruthy();
    const attempt = await login.json();
    const submitted = await request.post('/api/candidate/submit', { data: {}, headers: { Authorization: 'Bearer ' + attempt.exam_token } });
    expect(submitted.ok(), await submitted.text()).toBeTruthy();

    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(data.actor_email);
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await page.waitForURL(url => !url.pathname.startsWith('/login'));
    await page.goto('/results/exams/' + data.exam_id);
    await page.getByRole('button', { name: 'Schedule retake', exact: true }).click();
    const dialog = page.getByRole('dialog', { name: 'Schedule retake' });
    await expect(dialog).toBeVisible();
    const dates = await page.evaluate(() => {
        const local = (minutes: number) => {
            const date = new Date(Date.now() + minutes * 60000);
            return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
        };
        return { start: local(60), close: local(180) };
    });
    await dialog.getByLabel('Start date and time').fill(dates.start);
    await dialog.getByLabel('Closing date and time').fill(dates.close);
    await dialog.getByLabel('Duration in minutes').fill('130');
    await dialog.getByLabel('Reason', { exact: true }).fill('Browser retake approval');
    await dialog.getByRole('button', { name: 'Schedule retake', exact: true }).click();
    await expect(dialog.getByRole('alert')).toContainText('allow the full exam duration');
    await dialog.getByLabel('Duration in minutes').fill('30');
    await dialog.getByRole('button', { name: 'Schedule retake', exact: true }).click();
    await expect(dialog).not.toBeVisible();
    await expect(page.getByRole('button', { name: 'Cancel retake', exact: true })).toBeVisible();
    await expect(page.getByText('Retake scheduled', { exact: true })).toBeVisible();

    await page.getByRole('link', { name: 'View', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Attempt history', exact: true })).toBeVisible();
    await expect(page.getByText('Attempt 1 ? Current result', { exact: true })).toBeVisible();
    await expect(page.getByText('Reason: Browser retake approval', { exact: true })).toBeVisible();
    await page.goto('/results/exams/' + data.exam_id);
    await page.getByRole('button', { name: 'Cancel retake', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Schedule retake', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'View', exact: true })).toHaveCount(1);
    await page.getByRole('link', { name: 'View', exact: true }).click();
    await expect(page.getByText('Attempt 2 ? cancelled', { exact: true })).toBeVisible();
    await expect(page.getByText('Attempt 1 ? Current result', { exact: true })).toBeVisible();
});
