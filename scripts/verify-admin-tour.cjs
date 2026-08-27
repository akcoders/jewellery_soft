'use strict';

const playwrightPath = process.env.PLAYWRIGHT_CORE_PATH;
const profilePath = process.env.TOUR_PROFILE_PATH;
const baseUrl = process.env.TOUR_CHECK_URL || 'https://jewellery-soft.test/admin/dashboard';
const rememberCookie = process.env.TOUR_REMEMBER_COOKIE || '';
const viewportWidth = Number(process.env.TOUR_VIEWPORT_WIDTH || 1440);
const viewportHeight = Number(process.env.TOUR_VIEWPORT_HEIGHT || 900);
const testDismiss = process.env.TOUR_TEST_DISMISS === '1';
const testReplay = process.env.TOUR_TEST_REPLAY === '1';

if (!playwrightPath || !profilePath) {
    throw new Error('PLAYWRIGHT_CORE_PATH and TOUR_PROFILE_PATH are required.');
}

const { chromium } = require(playwrightPath);

(async () => {
    const context = await chromium.launchPersistentContext(profilePath, {
        headless: true,
        executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        viewport: { width: viewportWidth, height: viewportHeight },
        args: ['--disable-extensions', '--disable-background-networking'],
    });
    if (rememberCookie) {
        const target = new URL(baseUrl);
        await context.addCookies([{
            name: 'aabhushan_admin_remember',
            value: rememberCookie,
            domain: target.hostname,
            path: '/',
            httpOnly: true,
            secure: target.protocol === 'https:',
            sameSite: 'Lax',
        }]);
    }
    const page = context.pages()[0] || await context.newPage();
    await page.goto(baseUrl, { waitUntil: 'domcontentloaded', timeout: 45_000 });
    await page.waitForTimeout(8_000);
    if (testDismiss && await page.locator('.app-tour-card').isVisible().catch(() => false)) {
        await page.locator('[data-tour-action="next"]').click();
        await page.waitForTimeout(600);
        await page.locator('[data-tour-action="never"]').click();
        await page.locator('.app-tour-card').waitFor({ state: 'detached', timeout: 5_000 });
    }
    if (testReplay && ! await page.locator('.app-tour-card').isVisible().catch(() => false)) {
        await page.locator('.user-link').click();
        await page.locator('[data-app-tour-replay]').click();
        await page.locator('.app-tour-card').waitFor({ state: 'visible', timeout: 5_000 });
        await page.waitForTimeout(350);
    }

    const result = await page.evaluate(async () => {
        let api = null;
        try {
            if (!window.AabhushanTourConfig?.stateUrl) {
                throw new Error('Tour config is not available on this page.');
            }
            const response = await fetch(window.AabhushanTourConfig.stateUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            var contentType = response.headers.get('content-type') || '';
            api = contentType.includes('application/json')
                ? { status: response.status, data: await response.json() }
                : { status: response.status, error: 'Expected a JSON response.' };
        } catch (error) {
            api = { error: error.message };
        }

        return {
            url: window.location.href,
            title: document.title,
            replay: Boolean(document.querySelector('[data-app-tour-replay]')),
            config: Boolean(window.AabhushanTourConfig),
            tourCard: Boolean(document.querySelector('.app-tour-card')),
            tourTitle: document.querySelector('.app-tour-title')?.textContent || '',
            neverAction: document.querySelector('[data-tour-action="never"]')?.textContent || '',
            cardInsideViewport: (() => {
                const card = document.querySelector('.app-tour-card');
                if (!card) return false;
                const rect = card.getBoundingClientRect();
                return rect.left >= 0 && rect.top >= 0 && rect.right <= innerWidth && rect.bottom <= innerHeight;
            })(),
            api,
        };
    });

    process.stdout.write(`${JSON.stringify(result)}\n`);
    await context.close();
})().catch((error) => {
    process.stderr.write(`${error.message}\n`);
    process.exitCode = 1;
});
