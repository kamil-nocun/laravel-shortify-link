import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: 'tests/e2e',
    workers: 1,
    use: { baseURL: process.env.APP_URL ?? 'http://localhost:8000' },
});
