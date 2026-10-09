import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';

const password = 'password123';
const run = (code: string) =>
    execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString();

const userEmail = `e2e-stats-${Date.now()}@example.com`;
const storageState = `test-results/auth-stats-${Date.now()}.json`;

test.use({ storageState });

test.beforeAll(async ({ browser }) => {
    run(
        `$user = App\\Models\\User::factory()->create(['email'=>'${userEmail}','password'=>bcrypt('${password}')]); $link = App\\Models\\Link::factory()->for($user)->create(['title'=>'Stats e2e']); App\\Models\\LinkClick::factory()->count(3)->for($link)->create(); $filtered = App\\Models\\Link::factory()->for($user)->create(['title'=>'Filters e2e']); App\\Models\\LinkClick::factory()->count(2)->for($filtered)->create(['browser'=>'Chrome','country'=>'Poland','city'=>'Warsaw']); App\\Models\\LinkClick::factory()->for($filtered)->create(['browser'=>'Firefox','country'=>'Germany','city'=>'Berlin']);`,
    );

    const context = await browser.newContext({ storageState: undefined });
    const page = await context.newPage();
    await page.goto('/login');
    await page.getByLabel('Email address').fill(userEmail);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/dashboard/);
    await context.storageState({ path: storageState });
    await context.close();
});

test.afterAll(() => {
    run(`App\\Models\\User::where('email','${userEmail}')->delete();`);
});

test('stats page shows clicks and switches the range', async ({ page }) => {
    await page.goto('/links');
    await expect(
        page
            .getByRole('row', { name: /Stats e2e/ })
            .getByRole('cell')
            .nth(3),
    ).toHaveText('3');

    await page
        .getByRole('row', { name: /Stats e2e/ })
        .getByRole('link', { name: 'Stats' })
        .click();
    await expect(page.getByText('3 clicks in the last 30 days')).toBeVisible();
    await expect(
        page.getByRole('cell', { name: 'Warsaw, Poland' }),
    ).toBeVisible();
    for (const name of ['Chrome', 'Windows', 'desktop']) {
        await expect(
            page.getByRole('cell', { name, exact: true }),
        ).toBeVisible();
    }

    await page.getByRole('link', { name: '7 days' }).click();
    await expect(page).toHaveURL(/days=7/);
    await expect(page.getByText('3 clicks in the last 7 days')).toBeVisible();
});

test('stats can be filtered, filters removed one by one and reset', async ({
    page,
}) => {
    await page.goto('/links');
    await page
        .getByRole('row', { name: /Filters e2e/ })
        .getByRole('link', { name: 'Stats' })
        .click();
    await expect(page.getByText('3 clicks in the last 30 days')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Clear all' })).toHaveCount(
        0,
    );

    await page.getByLabel('Browser').selectOption('Firefox');
    await expect(page.getByText('1 clicks in the last 30 days')).toBeVisible();
    await expect(page).toHaveURL(/browser=Firefox/);

    await page.getByRole('link', { name: '7 days' }).click();
    await expect(page).toHaveURL(/days=7/);
    await expect(page).toHaveURL(/browser=Firefox/);

    await page.getByLabel('Country').selectOption('Germany');
    await expect(
        page.getByRole('button', { name: 'Remove Browser filter' }),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Remove Country filter' }),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Remove Browser filter' }).click();
    await expect(page).not.toHaveURL(/browser=/);
    await expect(page).toHaveURL(/country=Germany/);
    await expect(page.getByText('1 clicks in the last 7 days')).toBeVisible();

    await page.getByRole('button', { name: 'Clear all' }).click();
    await expect(page).not.toHaveURL(/country=/);
    await expect(page).toHaveURL(/days=7/);
    await expect(page.getByText('3 clicks in the last 7 days')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Clear all' })).toHaveCount(
        0,
    );
});
