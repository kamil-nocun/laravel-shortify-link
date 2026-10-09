import { expect, test, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';

const password = 'password123';
const run = (code: string) =>
    execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString();

const userEmail = `e2e-stats-${Date.now()}@example.com`;
const storageState = `test-results/auth-stats-${Date.now()}.json`;

test.use({ storageState });

test.beforeAll(async ({ browser }) => {
    run(
        `$user = App\\Models\\User::factory()->create(['email'=>'${userEmail}','password'=>bcrypt('${password}')]); $link = App\\Models\\Link::factory()->for($user)->create(['title'=>'Stats e2e']); App\\Models\\LinkClick::factory()->count(3)->for($link)->create(); $period = App\\Models\\Link::factory()->for($user)->create(['title'=>'Period e2e']); App\\Models\\LinkClick::factory()->count(3)->for($period)->create(); App\\Models\\LinkClick::factory()->count(4)->for($period)->create(['clicked_at'=>'2020-03-15 12:00:00']); App\\Models\\LinkClick::factory()->count(2)->for($period)->create(['clicked_at'=>'2021-06-10 12:00:00']); $pages = App\\Models\\Link::factory()->for($user)->create(['title'=>'Pages e2e']); foreach (range(1,25) as $i) { App\\Models\\LinkClick::factory()->for($pages)->create(['city'=>sprintf('City%02d',$i)]); } $filtered = App\\Models\\Link::factory()->for($user)->create(['title'=>'Filters e2e']); App\\Models\\LinkClick::factory()->count(2)->for($filtered)->create(['browser'=>'Chrome','country'=>'Poland','city'=>'Warsaw']); App\\Models\\LinkClick::factory()->for($filtered)->create(['browser'=>'Firefox','country'=>'Germany','city'=>'Berlin']);`,
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
    await expect(page).toHaveURL(/period=7d/);
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
    await expect(page).toHaveURL(/period=7d/);
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
    await expect(page).toHaveURL(/period=7d/);
    await expect(page.getByText('3 clicks in the last 7 days')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Clear all' })).toHaveCount(
        0,
    );
});

test('filter selects follow the page after browser back', async ({ page }) => {
    await page.goto('/links');
    await page
        .getByRole('row', { name: /Filters e2e/ })
        .getByRole('link', { name: 'Stats' })
        .click();

    await page.getByLabel('Browser').selectOption('Firefox');
    await expect(page).toHaveURL(/browser=Firefox/);
    await page.getByRole('button', { name: 'Clear all' }).click();
    await expect(page).not.toHaveURL(/browser=/);

    await page.goBack();

    await expect(page).toHaveURL(/browser=Firefox/);
    await expect(
        page.locator('label', { hasText: 'Browser' }).locator('select'),
    ).toHaveValue('Firefox');
    await expect(
        page.getByRole('button', { name: 'Remove Browser filter' }),
    ).toBeVisible();
});

const openStats = async (page: Page, title: string) => {
    await page.goto('/links');
    await page
        .getByRole('row', { name: new RegExp(title) })
        .getByRole('link', { name: 'Stats' })
        .click();
};
const section = (page: Page, title: string) =>
    page.locator('section', {
        has: page.getByRole('heading', { name: title, exact: true }),
    });

test('period can be switched to all time, a month and a range', async ({
    page,
}) => {
    await openStats(page, 'Period e2e');
    await expect(page.getByText('3 clicks in the last 30 days')).toBeVisible();

    await page.getByRole('link', { name: 'All time' }).click();
    await expect(page.getByText('9 clicks in total')).toBeVisible();

    await page.getByLabel('Custom period').selectOption('month');
    await page.getByLabel('Month', { exact: true }).fill('2020-03');
    await expect(page).toHaveURL(/period=month/);
    await expect(page).toHaveURL(/month=2020-03/);
    await expect(page.getByText('4 clicks in 2020-03')).toBeVisible();

    await page.getByLabel('Custom period').selectOption('range');
    await page.getByLabel('From', { exact: true }).fill('2021-06-01');
    await page.getByLabel('To', { exact: true }).fill('2021-06-30');
    await expect(page).toHaveURL(/period=range/);
    await expect(
        page.getByText('2 clicks in 2021-06-01 - 2021-06-30'),
    ).toBeVisible();
});

test('table can be sorted by name, toggling direction', async ({ page }) => {
    await openStats(page, 'Pages e2e');
    const cities = section(page, 'Cities');
    const header = cities.getByRole('button', { name: /^Name/ });

    await header.click();
    await expect(page).toHaveURL(/sort%5Bcities%5D=name_asc/);
    await expect(cities.getByRole('cell').first()).toHaveText(/^City01/);

    await header.click();
    await expect(page).toHaveURL(/sort%5Bcities%5D=name_desc/);
    await expect(cities.getByRole('cell').first()).toHaveText(/^City25/);
});

test('table paginates, limit changes and resets keep the limit', async ({
    page,
}) => {
    await openStats(page, 'Pages e2e');
    const cities = section(page, 'Cities');

    await expect(cities.getByText('Page 1 of 3 (25 rows)')).toBeVisible();
    await cities.getByRole('button', { name: 'Next' }).click();
    await expect(cities.getByText('Page 2 of 3 (25 rows)')).toBeVisible();

    await page.getByRole('link', { name: '7 days' }).click();
    await expect(cities.getByText('Page 1 of 3 (25 rows)')).toBeVisible();

    await cities.getByRole('button', { name: 'Next' }).click();
    await expect(cities.getByText('Page 2 of 3 (25 rows)')).toBeVisible();
    await cities.getByRole('button', { name: /^Name/ }).click();
    await expect(cities.getByText('Page 1 of 3 (25 rows)')).toBeVisible();

    await cities.getByLabel('Rows').selectOption('50');
    await expect(cities.getByText('Page 1 of 1 (25 rows)')).toBeVisible();
    await expect(cities.getByRole('button', { name: 'Next' })).toHaveCount(0);
    await expect(cities.getByRole('button', { name: 'Previous' })).toHaveCount(
        0,
    );

    await page.getByRole('link', { name: '30 days' }).click();
    await expect(cities.getByLabel('Rows')).toHaveValue('50');
});

test('an invalid range shows a validation message', async ({ page }) => {
    await openStats(page, 'Period e2e');
    await page.getByLabel('Custom period').selectOption('range');
    await page.getByLabel('From', { exact: true }).fill('2021-06-30');
    await page.getByLabel('To', { exact: true }).fill('2021-06-01');
    await expect(page.getByRole('alert')).toBeVisible();
});
