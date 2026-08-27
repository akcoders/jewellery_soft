#!/usr/bin/env node
'use strict';

const fs = require('node:fs/promises');
const os = require('node:os');
const path = require('node:path');
const { chromium } = require('playwright-core');

const baseUrl = new URL(
    (process.env.GUIDE_BASE_URL || 'https://aabhushan.webignitors.in/public').replace(/\/$/, '') + '/',
);
const outputDir = path.resolve(
    process.env.GUIDE_OUTPUT_DIR || 'docs/aabhushan-user-guide/screenshots',
);
const profileDir = path.resolve(process.env.GUIDE_PROFILE_DIR || '');
const chromePath = process.env.GUIDE_CHROME_PATH
    || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const liveChromeRoot = path.resolve(
    os.homedir(),
    'Library/Application Support/Google/Chrome',
);

if (! process.env.GUIDE_PROFILE_DIR) {
    throw new Error('GUIDE_PROFILE_DIR must point to an isolated Chrome profile clone.');
}
if (profileDir === liveChromeRoot || profileDir.startsWith(liveChromeRoot + path.sep)) {
    throw new Error('Refusing to use the live Chrome profile. Pass an isolated clone.');
}

const normalizeText = (value) => String(value || '').replace(/\s+/g, ' ').trim();
const unique = (values) => [...new Set(values.filter(Boolean))];

function pageId(urlValue) {
    const url = new URL(urlValue);
    const adminPrefix = `${baseUrl.pathname.replace(/\/$/, '')}/admin/`;
    const relative = url.pathname.startsWith(adminPrefix)
        ? url.pathname.slice(adminPrefix.length)
        : url.pathname;

    return relative
        .replace(/[^a-z0-9]+/gi, '-')
        .replace(/^-+|-+$/g, '')
        .toLowerCase() || 'dashboard';
}

async function waitForPage(page) {
    await page.locator('.page-wrapper').waitFor({ state: 'visible', timeout: 20_000 });
    await page.locator('#globalLoaderOverlay').waitFor({ state: 'hidden', timeout: 10_000 }).catch(() => {});
    await page.waitForLoadState('networkidle', { timeout: 6_000 }).catch(() => {});
    await page.evaluate(async () => {
        if (document.fonts && document.fonts.ready) {
            await document.fonts.ready;
        }
    });
    await page.waitForTimeout(500);
}

async function extractStructure(page) {
    return page.evaluate(() => {
        const text = (element) => (element?.textContent || '').replace(/\s+/g, ' ').trim();
        const uniqueText = (selector) => [...new Set(
            [...document.querySelectorAll(selector)].map(text).filter(Boolean),
        )];

        return {
            title: document.title,
            heading: text(document.querySelector(
                '.erp-page-toolbar h4, .content-page-header h5, .page-wrapper h1, .page-wrapper h2, .page-wrapper h3',
            )),
            description: text(document.querySelector('.erp-page-toolbar p')),
            sectionTitles: uniqueText('.card-title, .card-header h4, .card-header h5'),
            tableHeaders: [...document.querySelectorAll('.page-wrapper table')]
                .map((table) => [...table.querySelectorAll('thead th')].map(text).filter(Boolean))
                .filter((headers) => headers.length > 0),
            formLabels: uniqueText('.page-wrapper label'),
            actions: uniqueText('.page-wrapper a.btn, .page-wrapper button')
                .filter((label) => label.length < 100),
        };
    });
}

async function redactSensitiveFields(page) {
    await page.evaluate(() => {
        const sensitivePattern = /(password|passcode|secret|token|api[_ -]?key|rest[_ -]?api|app[_ -]?id|sender[_ -]?id|api[_ -]?url|auth[_ -]?header|alert[_ -]?numbers|extra[_ -]?headers|credential|webhook)/i;

        for (const field of document.querySelectorAll('input, textarea')) {
            const label = field.id
                ? document.querySelector(`label[for="${CSS.escape(field.id)}"]`)?.textContent || ''
                : '';
            const descriptor = [
                field.type,
                field.name,
                field.id,
                field.autocomplete,
                field.placeholder,
                label,
            ].join(' ');

            if (! sensitivePattern.test(descriptor)) {
                continue;
            }

            if (field.type !== 'checkbox' && field.type !== 'radio' && field.type !== 'file') {
                field.value = '';
                field.removeAttribute('value');
                field.setAttribute('placeholder', 'Hidden for documentation');
                field.classList.add('guide-redacted-field');
            }
        }

        for (const element of document.querySelectorAll('.user-name')) {
            element.textContent = 'Admin User';
        }

        window.scrollTo(0, 0);
        const sidebar = document.querySelector('.sidebar-inner');
        if (sidebar) sidebar.scrollTop = 0;
    });

    await page.addStyleTag({ content: `
        .guide-redacted-field {
            background-color: #f1f3f5 !important;
            background-image: repeating-linear-gradient(
                135deg,
                rgba(89, 96, 105, .12) 0,
                rgba(89, 96, 105, .12) 8px,
                rgba(255, 255, 255, .6) 8px,
                rgba(255, 255, 255, .6) 16px
            ) !important;
            color: transparent !important;
            text-shadow: none !important;
        }
    ` });
}

async function main() {
    await fs.mkdir(outputDir, { recursive: true });

    const context = await chromium.launchPersistentContext(profileDir, {
        headless: process.env.GUIDE_HEADLESS !== '0',
        executablePath: chromePath,
        // Playwright normally adds --use-mock-keychain on macOS. The isolated
        // cookie clone was encrypted by Chrome's real keychain, so omit it.
        ignoreDefaultArgs: ['--use-mock-keychain'],
        viewport: { width: 1600, height: 1000 },
        locale: 'en-IN',
        timezoneId: 'Asia/Kolkata',
        acceptDownloads: false,
        args: [
            '--profile-directory=Default',
            '--hide-scrollbars',
            '--disable-features=PasswordLeakDetection',
        ],
    });

    const page = context.pages()[0] || await context.newPage();
    page.setDefaultTimeout(20_000);
    page.on('dialog', (dialog) => dialog.dismiss().catch(() => {}));
    page.on('download', (download) => download.cancel().catch(() => {}));

    const dashboardUrl = new URL('admin/dashboard', baseUrl).href;
    await page.goto(dashboardUrl, { waitUntil: 'domcontentloaded', timeout: 45_000 });
    await Promise.race([
        page.locator('#sidebar-menu').waitFor({ state: 'attached', timeout: 45_000 }),
        page.locator('input[name="email"]').waitFor({ state: 'visible', timeout: 45_000 }),
    ]);

    if (page.url().includes('/admin/login') || await page.locator('#sidebar-menu').count() === 0) {
        throw new Error('The isolated profile is not authenticated for the admin application.');
    }

    const rawLinks = await page.locator('#sidebar-menu a[href]').evaluateAll((anchors) => anchors.map((anchor) => {
        const topLevelItem = anchor.closest('ul.sidebar-vertical > li');
        let group = '';
        for (let sibling = topLevelItem?.previousElementSibling; sibling; sibling = sibling.previousElementSibling) {
            if (sibling.classList.contains('menu-title')) {
                group = (sibling.textContent || '').replace(/\s+/g, ' ').trim();
                break;
            }
        }

        const submenuItem = anchor.closest('li.submenu');
        const submenu = submenuItem
            ? (submenuItem.querySelector(':scope > a > span')?.textContent || '').replace(/\s+/g, ' ').trim()
            : '';

        return {
            href: anchor.href,
            label: (anchor.textContent || '').replace(/\s+/g, ' ').trim(),
            group,
            submenu,
        };
    }));

    const seen = new Set();
    const adminPrefix = `${baseUrl.pathname.replace(/\/$/, '')}/admin/`;
    const pages = rawLinks.filter((item) => {
        let url;
        try {
            url = new URL(item.href);
        } catch {
            return false;
        }

        if (url.origin !== baseUrl.origin || ! url.pathname.startsWith(adminPrefix)) return false;
        if (/\/(?:login|logout|register)\/?$/.test(url.pathname)) return false;
        if (url.search || url.hash || seen.has(url.href)) return false;
        seen.add(url.href);
        return true;
    });

    console.log(`Discovered ${pages.length} unique read-only sidebar pages.`);

    const manifest = [];
    for (let index = 0; index < pages.length; index += 1) {
        const item = pages[index];
        const id = pageId(item.href);
        const filename = `${String(index + 1).padStart(2, '0')}-${id}.png`;
        const record = {
            id,
            path: new URL(item.href).pathname,
            url: item.href,
            label: normalizeText(item.label),
            group: normalizeText(item.group),
            submenu: normalizeText(item.submenu),
            httpStatus: null,
            screenshot: filename,
            title: '',
            heading: '',
            description: '',
            sectionTitles: [],
            tableHeaders: [],
            formLabels: [],
            actions: [],
            error: null,
        };

        try {
            const response = await page.goto(item.href, {
                waitUntil: 'domcontentloaded',
                timeout: 45_000,
            });
            record.httpStatus = response?.status() || null;

            if (page.url().includes('/admin/login')) {
                throw new Error('Authentication expired during capture.');
            }

            await waitForPage(page);
            Object.assign(record, await extractStructure(page));
            await redactSensitiveFields(page);
            await page.screenshot({
                path: path.join(outputDir, filename),
                fullPage: true,
                animations: 'disabled',
            });
            console.log(`[${index + 1}/${pages.length}] ${id} (${record.httpStatus || 'no status'})`);
        } catch (error) {
            record.error = normalizeText(error?.message || error);
            console.error(`[${index + 1}/${pages.length}] ${id}: ${record.error}`);
        }

        manifest.push(record);
    }

    await fs.writeFile(
        path.join(outputDir, 'pages.json'),
        `${JSON.stringify(manifest, null, 2)}\n`,
        'utf8',
    );

    await context.close();

    const successful = manifest.filter((item) => ! item.error).length;
    console.log(`Captured ${successful}/${manifest.length} pages into ${outputDir}.`);
    if (successful !== manifest.length) process.exitCode = 2;
}

main().catch((error) => {
    console.error(normalizeText(error?.message || error));
    process.exitCode = 1;
});
