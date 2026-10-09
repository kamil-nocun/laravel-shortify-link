import { expect, test } from '@playwright/test';
import { execFileSync } from 'node:child_process';

const run = (code: string) =>
    execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString();

const suffix = Date.now().toString(36);
const activeCode = `A${suffix}`;
const inactiveCode = `I${suffix}`;

const createLink = (code: string, attributes: string) =>
    run(
        `App\\Models\\Link::factory()${attributes}->create(["code"=>"${code}","original_url"=>"https://example.com/target"]);`,
    );

test.beforeAll(() => {
    createLink(activeCode, '');
    createLink(inactiveCode, '->inactive()');
});

test.afterAll(() => {
    run(
        `foreach (App\\Models\\Link::whereIn("code",["${activeCode}","${inactiveCode}"])->get() as $link) { $user = $link->user; $link->delete(); $user->delete(); }`,
    );
});

test('active short link redirects to the target', async ({ page }) => {
    await page.route('https://example.com/**', (route) =>
        route.fulfill({ body: 'target' }),
    );

    await page.goto(`/${activeCode}`);

    await expect(page).toHaveURL('https://example.com/target');
});

test('inactive short link shows the not found page', async ({ page }) => {
    const response = await page.goto(`/${inactiveCode}`);

    expect(response?.status()).toBe(404);
    await expect(
        page.getByText('This link does not exist or is no longer available.'),
    ).toBeVisible();
});
