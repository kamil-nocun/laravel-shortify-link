import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';

const password = 'password123';
const run = (code: string) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString();

const userEmail = `e2e-${Date.now()}@example.com`;
const otherEmail = `e2e-other-${Date.now()}@example.com`;

const storageState = `test-results/auth-${Date.now()}.json`;

test.use({ storageState });

test.beforeAll(async ({ browser }) => {
    run(`App\\Models\\User::factory()->create(["email"=>"${userEmail}","password"=>bcrypt("${password}")]);`);

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
    run(`App\\Models\\User::whereIn("email",["${userEmail}","${otherEmail}"])->delete();`);
});

test.afterEach(() => {
    run(`App\\Models\\User::where("email","${userEmail}")->first()->links()->delete();`);
});

test('guest is redirected to login', async ({ browser }) => {
    const page = await (await browser.newContext({ storageState: undefined })).newPage();
    await page.goto('/links');
    await expect(page).toHaveURL(/login/);
});

test('create form requires target URL with a friendly name', async ({ page }) => {
    await page.goto('/links/create');
    await page.locator('form').evaluate((form: HTMLFormElement) => (form.noValidate = true));
    await page.getByRole('button', { name: 'Create link' }).click();
    await expect(page.getByText('The target URL field is required.')).toBeVisible();
});

test('create form rejects non-http schemes and past expiry', async ({ page }) => {
    await page.goto('/links/create');
    await page.getByLabel('Target URL').fill('ftp://example.com/file');
    await page.getByRole('button', { name: 'Create link' }).click();
    await expect(page.getByText('must be a valid URL')).toBeVisible();

    await page.getByLabel('Target URL').fill('https://example.com');
    await page.getByLabel('Expires at').click();
    await page.getByRole('button', { name: 'Previous month' }).click();
    await page.locator('[data-reka-calendar-cell-trigger]:not([data-outside-view])').first().click();
    await page.keyboard.press('Escape');
    await page.getByRole('button', { name: 'Create link' }).click();
    await expect(page.getByText('The expiry date field must be a date after now')).toBeVisible();
});

test('expires at is picked from a calendar and shown in the list', async ({ page }) => {
    await page.goto('/links/create');
    await page.getByLabel('Target URL').fill('https://example.com/cal');
    await page.getByLabel('Title').fill('E2E calendar');
    await page.getByLabel('Expires at').click();
    await page.getByRole('button', { name: 'Next month' }).click();
    await page.locator('[data-reka-calendar-cell-trigger]:not([data-outside-view])').first().click();
    await page.keyboard.press('Escape');
    await page.getByRole('button', { name: 'Create link' }).click();

    await expect(page).toHaveURL(/\/links$/);
    await expect(page.getByRole('row', { name: /E2E calendar/ })).not.toContainText('—');
});

test('create, edit and delete a link', async ({ page }) => {
    await page.goto('/links/create');
    await page.getByLabel('Target URL').fill('https://example.com/a');
    await page.getByLabel('Title').fill('E2E link');
    await page.getByRole('button', { name: 'Create link' }).click();

    await expect(page).toHaveURL(/\/links$/);
    await expect(page.getByText('Link created.')).toBeVisible();
    const row = page.getByRole('row', { name: /E2E link/ });
    await expect(row).toContainText('https://example.com/a');
    await expect(row).toContainText('Active');
    const shortUrl = await row.getByText(/^https?:\/\/.+\/\w{6}$/).innerText();

    await row.getByRole('link', { name: 'Edit' }).click();
    await page.getByLabel('Title').fill('E2E renamed');
    await page.getByRole('checkbox', { name: 'Active' }).uncheck();
    await page.getByRole('button', { name: 'Save changes' }).click();

    await expect(page.getByText('Link updated.')).toBeVisible();
    const renamed = page.getByRole('row', { name: /E2E renamed/ });
    await expect(renamed).toContainText('Inactive');
    await expect(renamed).toContainText(shortUrl);

    await renamed.getByRole('button', { name: 'Delete' }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(page.getByText('You have no links yet.')).toBeVisible();
});

test("another user's link cannot be opened for editing", async ({ page }) => {
    const id = run(
        `echo App\\Models\\Link::factory()->for(App\\Models\\User::factory()->create(["email"=>"${otherEmail}"]))->create()->id;`,
    ).trim();

    await page.goto(`/links/${id}/edit`);
    await expect(page).toHaveURL(/\/links$/);
});
