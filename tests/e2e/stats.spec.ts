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

    await page.getByRole('button', { name: '7 days' }).click();
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

    await page.getByRole('button', { name: '7 days' }).click();

    await page.getByLabel('Country').selectOption('Germany');
    await expect(
        page.getByRole('button', { name: 'Remove Browser filter' }),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Remove Country filter' }),
    ).toBeVisible();

    await page.getByRole('button', { name: 'Remove Browser filter' }).click();
    await expect(page.getByText('1 clicks in the last 7 days')).toBeVisible();

    await page.getByRole('button', { name: 'Clear all' }).click();
    await expect(page.getByText('3 clicks in the last 7 days')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Clear all' })).toHaveCount(
        0,
    );
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

    await page.getByRole('button', { name: 'All time' }).click();
    await expect(page.getByText('9 clicks in total')).toBeVisible();

    await page.getByLabel('Custom period').selectOption('month');
    await page.getByLabel('Month', { exact: true }).fill('2020-03');
    await expect(page.getByText('4 clicks in 2020-03')).toBeVisible();

    await page.getByLabel('Custom period').selectOption('range');
    await page.getByLabel('From', { exact: true }).fill('2021-06-01');
    await page.getByLabel('To', { exact: true }).fill('2021-06-30');
    await expect(
        page.getByText('2 clicks in 2021-06-01 - 2021-06-30'),
    ).toBeVisible();
});

test('table can be sorted by name, toggling direction', async ({ page }) => {
    await openStats(page, 'Pages e2e');
    const cities = section(page, 'Cities');
    await cities.getByRole('button', { name: 'Cities table view' }).click();
    const header = cities.getByRole('button', { name: /^City/ });

    await header.click();
    await expect(cities.getByRole('cell').first()).toHaveText(/^City01/);

    await header.click();
    await expect(cities.getByRole('cell').first()).toHaveText(/^City25/);
});

test('table paginates, limit changes and resets keep the limit', async ({
    page,
}) => {
    await openStats(page, 'Pages e2e');
    const cities = section(page, 'Cities');
    await cities.getByRole('button', { name: 'Cities table view' }).click();

    await expect(cities.getByText('Page 1 of 3 (25 rows)')).toBeVisible();
    await cities.getByRole('button', { name: 'Next' }).click();
    await expect(cities.getByText('Page 2 of 3 (25 rows)')).toBeVisible();

    await page.getByRole('button', { name: '7 days' }).click();
    await expect(cities.getByText('Page 1 of 3 (25 rows)')).toBeVisible();

    await cities.getByRole('button', { name: 'Next' }).click();
    await expect(cities.getByText('Page 2 of 3 (25 rows)')).toBeVisible();
    await cities.getByRole('button', { name: /^City/ }).click();
    await expect(cities.getByText('Page 1 of 3 (25 rows)')).toBeVisible();

    await cities.getByLabel('Rows').selectOption('50');
    await expect(cities.getByText('Page 1 of 1 (25 rows)')).toBeVisible();
    await expect(cities.getByRole('button', { name: 'Next' })).toHaveCount(0);
    await expect(cities.getByRole('button', { name: 'Previous' })).toHaveCount(
        0,
    );

    await page.getByRole('button', { name: '30 days' }).click();
    await expect(cities.getByLabel('Rows')).toHaveValue('50');
});

test('interactions never change the URL and reload restores defaults', async ({
    page,
}) => {
    await openStats(page, 'Pages e2e');
    await expect(page).toHaveURL(/\/stats$/);
    const url = page.url();
    expect(url).toMatch(/\/links\/[^/?]+\/stats$/);
    const cities = section(page, 'Cities');
    await cities.getByRole('button', { name: 'Cities table view' }).click();

    await page.getByRole('button', { name: '7 days' }).click();
    await cities.getByRole('button', { name: 'Next' }).click();
    await cities.getByRole('button', { name: /^City/ }).click();
    await cities.getByLabel('Rows').selectOption('25');
    await expect(cities.getByText('Page 1 of 1 (25 rows)')).toBeVisible();
    await page.getByLabel('Country').selectOption({ index: 1 });
    await expect(page.getByRole('button', { name: 'Clear all' })).toBeVisible();
    expect(page.url()).toBe(url);

    await page.reload();
    await expect(page.getByText('25 clicks in the last 30 days')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Clear all' })).toHaveCount(
        0,
    );
    await expect(cities.getByLabel('Rows')).toHaveValue('10');
});

test('table actions request only their section, period requests all', async ({
    page,
}) => {
    await openStats(page, 'Pages e2e');
    const cities = section(page, 'Cities');
    await cities.getByRole('button', { name: 'Cities table view' }).click();

    let request = page.waitForRequest('**/stats/data?*');
    await cities.getByRole('button', { name: 'Next' }).click();
    let url = new URL((await request).url());
    expect(url.searchParams.getAll('sections[]')).toEqual(['cities']);

    request = page.waitForRequest('**/stats/data?*');
    await cities.getByLabel('Rows').selectOption('25');
    url = new URL((await request).url());
    expect(url.searchParams.getAll('sections[]')).toEqual(['cities']);

    request = page.waitForRequest('**/stats/data?*');
    await page.getByRole('button', { name: '7 days' }).click();
    url = new URL((await request).url());
    expect(url.searchParams.getAll('sections[]')).toEqual([]);
});

test('a newer request supersedes a slow in-flight one', async ({ page }) => {
    await openStats(page, 'Period e2e');
    let first = true;
    let slowFinished!: () => void;
    const slowDone = new Promise<void>((resolve) => (slowFinished = resolve));
    await page.route('**/stats/data?*', async (route) => {
        if (first) {
            first = false;
            await new Promise((resolve) => setTimeout(resolve, 1000));
            await route.continue().catch(() => {});
            slowFinished();

            return;
        }
        await route.continue();
    });

    await page.getByRole('button', { name: '7 days' }).click();
    await page.getByRole('button', { name: 'All time' }).click();
    await expect(page.getByText('9 clicks in total')).toBeVisible();
    await slowDone;
    await expect(page.getByText('9 clicks in total')).toBeVisible();
});

test('an invalid range shows its error, keeps data and clears on a valid range', async ({
    page,
}) => {
    await openStats(page, 'Period e2e');
    await page.getByLabel('Custom period').selectOption('range');
    await page.getByLabel('From', { exact: true }).fill('2021-06-30');
    await page.getByLabel('To', { exact: true }).fill('2021-06-01');

    const alert = page.getByRole('alert').first();
    await expect(alert).toBeVisible();
    await expect(alert).toHaveText(/\S/);
    const described = await page
        .locator('input[aria-label="From"], input[aria-label="To"]')
        .evaluateAll((inputs) =>
            inputs.map((input) => input.getAttribute('aria-describedby')),
        );
    const id = await alert.getAttribute('id');
    expect(described).toContain(id);
    await expect(page.locator(`#${id}`)).toHaveText(await alert.innerText());
    await expect(page.getByText('3 clicks in the last 30 days')).toBeVisible();

    await page.getByLabel('From', { exact: true }).fill('2021-06-01');
    await page.getByLabel('To', { exact: true }).fill('2021-06-30');
    await expect(
        page.getByText('2 clicks in 2021-06-01 - 2021-06-30'),
    ).toBeVisible();
    await expect(page.getByRole('alert')).toHaveCount(0);
});

test('tables are shown in two columns in order', async ({ page }) => {
    await openStats(page, 'Period e2e');
    const titles = [
        'Countries',
        'Cities',
        'Browsers',
        'Operating systems',
        'Devices',
    ];
    const boxes = [];
    for (const title of titles) {
        boxes.push(
            await page
                .getByRole('heading', { name: title, exact: true })
                .boundingBox(),
        );
    }
    const [countries, cities, browsers, systems, devices] = boxes;
    expect(cities!.y).toBe(countries!.y);
    expect(cities!.x).toBeGreaterThan(countries!.x);
    expect(browsers!.y).toBeGreaterThan(countries!.y);
    expect(browsers!.x).toBe(countries!.x);
    expect(systems!.y).toBe(browsers!.y);
    expect(systems!.x).toBeGreaterThan(browsers!.x);
    expect(devices!.y).toBeGreaterThan(browsers!.y);
});

test('share column shows the percentage of period clicks', async ({ page }) => {
    await openStats(page, 'Filters e2e');
    const cities = section(page, 'Cities');
    await cities.getByRole('button', { name: 'Cities table view' }).click();

    await expect(
        cities
            .getByRole('row', { name: /Berlin/ })
            .getByRole('cell')
            .nth(2),
    ).toHaveText('33.3%');
    await expect(
        cities
            .getByRole('row', { name: /Warsaw/ })
            .getByRole('cell')
            .nth(2),
    ).toHaveText('66.7%');
    await expect(cities.getByText('Page 1 of 1 (2 rows)')).toBeVisible();
});

test('filter selects follow the state after removing a chip and clearing', async ({
    page,
}) => {
    await openStats(page, 'Filters e2e');
    const select = (label: string) =>
        page.locator('label', { hasText: label }).locator('select');

    await page.getByLabel('Browser').selectOption('Firefox');
    await page.getByLabel('Country').selectOption('Germany');
    await expect(select('Browser')).toHaveValue('Firefox');

    await page.getByRole('button', { name: 'Remove Browser filter' }).click();
    await expect(select('Browser')).toHaveValue('');
    await expect(select('Country')).toHaveValue('Germany');

    await page.getByRole('button', { name: 'Clear all' }).click();
    await expect(select('Country')).toHaveValue('');
    await expect(
        page.getByRole('button', { name: 'Remove Country filter' }),
    ).toHaveCount(0);
});

test('a server-clamped page is not resent by other tables', async ({
    page,
}) => {
    await openStats(page, 'Pages e2e');
    const cities = section(page, 'Cities');
    await cities.getByRole('button', { name: 'Cities table view' }).click();
    await page.route('**/stats/data?*', async (route) => {
        const response = await route.fetch();
        const body = await response.json();
        if (body.cities) {
            body.cities.page = 1;
        }
        await route.fulfill({ response, json: body });
    });

    // "Page 1 of 3" is also the initial state, so wait for the clamped response itself
    const clamped = page.waitForResponse('**/stats/data?*');
    await cities.getByRole('button', { name: 'Next' }).click();
    await clamped;
    await page.evaluate(() => new Promise(requestAnimationFrame));
    await expect(cities.getByText('Page 1 of 3 (25 rows)')).toBeVisible();
    const request = page.waitForRequest('**/stats/data?*');
    await section(page, 'Countries')
        .getByRole('button', { name: 'Countries table view' })
        .click();
    await section(page, 'Countries')
        .getByRole('button', { name: /^Country/ })
        .click();
    expect(new URL((await request).url()).searchParams.get('cities_page')).toBe(
        '1',
    );
    await page.unrouteAll({ behavior: 'ignoreErrors' });
});

test('a failed request shows an alert, keeps data and clears on success', async ({
    page,
}) => {
    await openStats(page, 'Period e2e');
    await expect(page.getByText('3 clicks in the last 30 days')).toBeVisible();
    await page.route('**/stats/data?*', (route) =>
        route.fulfill({ status: 500 }),
    );

    await page.getByRole('button', { name: '7 days' }).click();
    await expect(page.getByRole('alert')).toHaveText(
        "Couldn't load stats. Try again.",
    );
    await expect(page.getByText('3 clicks in the last 30 days')).toBeVisible();

    await page.unroute('**/stats/data?*');
    await page.getByRole('button', { name: 'All time' }).click();
    await expect(page.getByText('9 clicks in total')).toBeVisible();
    await expect(page.getByRole('alert')).toHaveCount(0);
});

test('two quick Next clicks advance two pages', async ({ page }) => {
    await openStats(page, 'Pages e2e');
    const cities = section(page, 'Cities');
    await cities.getByRole('button', { name: 'Cities table view' }).click();

    await cities.getByRole('button', { name: 'Next' }).dblclick();
    await expect(cities.getByText('Page 3 of 3 (25 rows)')).toBeVisible();
});
