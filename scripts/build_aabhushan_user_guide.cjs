#!/usr/bin/env node
'use strict';

const fs = require('node:fs');
const path = require('node:path');

let marked;
try {
    ({ marked } = require('marked'));
} catch {
    throw new Error('The guide builder requires the "marked" package. Install it in a temporary Node runtime and expose it through NODE_PATH.');
}

const guideDir = path.resolve(process.env.GUIDE_DIR || 'docs/aabhushan-user-guide');
const sourcePath = path.join(guideDir, 'README.md');
const manifestPath = path.join(guideDir, 'screenshots/pages.json');
const markdownOutputPath = path.join(guideDir, 'Aabhushan-User-Guide.md');
const htmlOutputPath = path.join(guideDir, 'Aabhushan-User-Guide.html');

const source = fs.readFileSync(sourcePath, 'utf8');
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const pagesById = new Map(manifest.map((page) => [page.id, page]));

const escapeHtml = (value) => String(value || '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;');

const tokenPattern = /\{\{SCREENSHOT:([a-z0-9-]+)\}\}/g;
const tokens = [...source.matchAll(tokenPattern)].map((match) => match[1]);
const missing = tokens.filter((id) => ! pagesById.has(id));
if (missing.length > 0) {
    throw new Error(`Missing screenshots for: ${[...new Set(missing)].join(', ')}`);
}

const resolvedMarkdown = source.replace(tokenPattern, (_token, id) => {
    const page = pagesById.get(id);
    const alt = `${page.heading || page.label || id} page`;
    return `![${alt}](screenshots/${page.screenshot})`;
});
fs.writeFileSync(markdownOutputPath, resolvedMarkdown, 'utf8');

const welcomeAt = source.indexOf('## Welcome');
const bodySource = (welcomeAt >= 0 ? source.slice(welcomeAt) : source).replace(
    tokenPattern,
    (_token, id) => {
        const page = pagesById.get(id);
        const title = page.heading || page.label || id;
        return [
            '<figure class="app-screen">',
            `  <img src="screenshots/${escapeHtml(page.screenshot)}" alt="${escapeHtml(title)} page screenshot">`,
            `  <figcaption>${escapeHtml(title)} · <code>${escapeHtml(page.path)}</code></figcaption>`,
            '</figure>',
        ].join('\n');
    },
);

let bodyHtml = marked.parse(bodySource, { gfm: true });
const slugCounts = new Map();
const headings = [];
bodyHtml = bodyHtml.replace(/<h([1-3])>([\s\S]*?)<\/h\1>/g, (_match, level, inner) => {
    const plain = inner.replace(/<[^>]+>/g, '').replace(/&[^;]+;/g, ' ').replace(/\s+/g, ' ').trim();
    let slug = plain.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'section';
    const count = slugCounts.get(slug) || 0;
    slugCounts.set(slug, count + 1);
    if (count > 0) slug = `${slug}-${count + 1}`;
    if (Number(level) <= 2) headings.push({ level: Number(level), title: plain, slug });
    return `<h${level} id="${slug}">${inner}</h${level}>`;
});

const toc = headings.map((heading) => (
    `<li class="toc-level-${heading.level}"><a href="#${heading.slug}">${escapeHtml(heading.title)}</a></li>`
)).join('\n');
const generatedOn = new Intl.DateTimeFormat('en-IN', {
    dateStyle: 'long',
    timeZone: 'Asia/Kolkata',
}).format(new Date());

const html = `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Aabhushan Jewellery ERP — Administrator & Operations User Guide</title>
  <style>
    :root { --red:#b51220; --red-dark:#84101a; --gold:#c6951c; --ink:#24153b; --muted:#667085; --line:#e5e7eb; --paper:#fff; --wash:#f6f7fa; }
    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body { margin:0; background:var(--wash); color:#222936; font:15px/1.62 -apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif; }
    .cover { min-height:92vh; padding:9vh 9vw; display:flex; flex-direction:column; justify-content:center; color:white; background:linear-gradient(135deg,#790d18 0%,#bd1523 54%,#cb6f23 100%); position:relative; overflow:hidden; }
    .cover::after { content:""; position:absolute; width:520px; height:520px; border:2px solid rgba(255,255,255,.16); border-radius:50%; right:-170px; top:-190px; box-shadow:0 0 0 70px rgba(255,255,255,.035),0 0 0 140px rgba(255,255,255,.025); }
    .cover img { width:260px; max-width:58vw; background:white; border-radius:14px; padding:14px 20px; margin-bottom:48px; box-shadow:0 18px 45px rgba(0,0,0,.18); }
    .cover h1 { color:white; border:0; font-size:46px; line-height:1.08; max-width:820px; margin:0 0 14px; }
    .cover h2 { color:#ffdf8b; font-size:25px; margin:0 0 34px; font-weight:650; }
    .cover p { max-width:700px; font-size:17px; color:rgba(255,255,255,.86); }
    .cover .meta { margin-top:44px; padding-top:22px; border-top:1px solid rgba(255,255,255,.28); font-size:13px; letter-spacing:.03em; }
    .layout { max-width:1180px; margin:0 auto; background:var(--paper); box-shadow:0 20px 65px rgba(30,35,50,.1); }
    nav { padding:48px 64px; background:#fffaf0; border-bottom:1px solid #f0dfb6; }
    nav h2 { margin-top:0; color:var(--ink); }
    nav ol { columns:2; column-gap:44px; margin:0; padding:0; list-style:none; }
    nav li { break-inside:avoid; margin:0 0 7px; }
    nav .toc-level-1 { margin-top:14px; font-weight:750; color:var(--red-dark); }
    nav .toc-level-2 { padding-left:14px; font-size:13px; }
    nav a { color:inherit; text-decoration:none; }
    main { padding:56px 64px 80px; }
    h1,h2,h3 { color:var(--ink); line-height:1.22; scroll-margin-top:24px; }
    main h1 { margin:72px 0 24px; padding:16px 20px; border-left:5px solid var(--red); background:linear-gradient(90deg,#fff7f7,#fffaf0); font-size:30px; break-after:avoid; }
    main h1:first-child { margin-top:0; }
    main h2 { margin:48px 0 14px; font-size:23px; break-after:avoid; }
    main h3 { margin:30px 0 10px; font-size:18px; break-after:avoid; }
    p { margin:0 0 14px; }
    strong { color:#2c2140; }
    code { color:#8e1822; background:#fff2f1; border:1px solid #f6dcda; border-radius:5px; padding:2px 6px; font-size:.9em; overflow-wrap:anywhere; }
    ul,ol { margin:8px 0 20px; padding-left:25px; }
    li { margin-bottom:5px; }
    hr { border:0; border-top:1px solid var(--line); margin:34px 0; }
    .app-screen { margin:22px 0 26px; padding:10px; border:1px solid #e8e1d3; border-radius:13px; background:#fff; box-shadow:0 10px 30px rgba(35,28,50,.08); break-inside:avoid; page-break-inside:avoid; }
    .app-screen img { display:block; width:100%; max-height:244mm; object-fit:contain; object-position:top; border-radius:8px; border:1px solid #edf0f4; }
    .app-screen figcaption { padding:9px 5px 2px; color:var(--muted); font-size:12px; }
    table { width:100%; border-collapse:collapse; margin:18px 0 28px; font-size:13px; }
    th { color:white; background:var(--red-dark); text-align:left; }
    th,td { padding:9px 10px; border:1px solid #dddfe5; vertical-align:top; }
    tr:nth-child(even) td { background:#faf7f2; }
    .footer-note { margin-top:70px; padding:20px; color:var(--muted); background:#f8f8fa; border-radius:10px; font-size:12px; }
    @media (max-width:760px) { .cover{padding:70px 28px}.cover h1{font-size:36px}nav,main{padding:34px 24px}nav ol{columns:1}.layout{box-shadow:none} }
    @media print {
      @page { size:A4; margin:13mm 12mm 14mm; }
      @page:first { size:A4; margin:0; }
      body { background:white; font-size:10.5pt; line-height:1.48; print-color-adjust:exact; -webkit-print-color-adjust:exact; }
      .cover { width:210mm; height:297mm; min-height:297mm; margin:0; padding:40mm 24mm; break-after:page; page-break-after:always; }
      .cover h1 { font-size:38pt; }
      .layout { max-width:none; box-shadow:none; }
      nav { padding:12mm 10mm; break-after:page; page-break-after:always; }
      nav ol { columns:2; }
      main { padding:0; }
      main h1 { margin-top:12mm; font-size:22pt; break-before:page; page-break-before:always; }
      main h1:first-child { break-before:auto; page-break-before:auto; }
      main h2 { font-size:16pt; margin-top:9mm; }
      main h3 { font-size:13pt; }
      .app-screen { box-shadow:none; margin:5mm 0 6mm; padding:2mm; }
      .app-screen img { max-height:235mm; }
      a { color:inherit; }
    }
  </style>
</head>
<body>
  <section class="cover">
    <img src="../../public/template/assets/img/logo.png" alt="Aabhushan logo">
    <h1>Aabhushan Jewellery ERP</h1>
    <h2>Administrator &amp; Operations User Guide</h2>
    <p>A practical visual guide to customers, orders, production, inventory, showroom, accounts, reports, staff and administration.</p>
    <p class="meta">Portal: aabhushan.webignitors.in · 68 application pages · Generated ${escapeHtml(generatedOn)}</p>
  </section>
  <div class="layout">
    <nav aria-label="Contents">
      <h2>Contents</h2>
      <ol>${toc}</ol>
    </nav>
    <main>${bodyHtml}
      <p class="footer-note">Screens can vary by role permissions and later application updates. Integration credentials were intentionally hidden in the screenshots. Contact your administrator when an expected menu or action is unavailable.</p>
    </main>
  </div>
</body>
</html>
`;

fs.writeFileSync(htmlOutputPath, html, 'utf8');
console.log(JSON.stringify({
    pages: manifest.length,
    screenshotTokens: tokens.length,
    markdown: markdownOutputPath,
    html: htmlOutputPath,
}));
