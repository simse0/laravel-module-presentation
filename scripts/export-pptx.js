#!/usr/bin/env node

/**
 * Presentation PPTX Export – Hybrid: native text + chart screenshots
 *
 * Reads a JSON manifest (slides, textboxes, images) and builds a .pptx file.
 * For slides flagged with needsScreenshot, Puppeteer captures the .slide-content
 * area and embeds it as an image.
 *
 * Usage: node export-pptx.js <render-url> <manifest-path> <output-path> [chrome-path]
 */

const puppeteer = require('puppeteer');
const PptxGenJS = require('pptxgenjs');
const { readFileSync, existsSync } = require('fs');
const path = require('path');

const [,, renderUrl, manifestPath, outputPath, chromePath] = process.argv;

if (!renderUrl || !manifestPath || !outputPath) {
    console.error('Usage: export-pptx.js <render-url> <manifest-path> <output-path> [chrome-path]');
    process.exit(1);
}

const PX_TO_INCH = 1 / 96;
const CHART_WAIT_MS = 800;
const INITIAL_WAIT_MS = 1500;

function stripHtml(html) {
    if (!html) return '';
    return html
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<\/?(p|div)[^>]*>/gi, '\n')
        .replace(/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/gi, '$2')
        .replace(/<[^>]+>/g, '')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&#039;/g, "'")
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

function hexColor(c) {
    if (!c) return '000000';
    return c.replace(/^#/, '');
}

function fontWeight(w) {
    return (w || 400) >= 700;
}

(async () => {
    const manifest = JSON.parse(readFileSync(manifestPath, 'utf-8'));
    const { slideWidth, slideHeight, fontFamily, slides } = manifest;

    const pptxW = slideWidth * PX_TO_INCH;
    const pptxH = slideHeight * PX_TO_INCH;

    const needsBrowser = slides.some(s => s.needsScreenshot);
    let browser, page;

    if (needsBrowser) {
        const launchOptions = {
            headless: 'new',
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--font-render-hinting=none',
            ],
        };
        if (chromePath) launchOptions.executablePath = chromePath;

        browser = await puppeteer.launch(launchOptions);
        page = await browser.newPage();
        await page.setViewport({ width: 1920, height: 1080, deviceScaleFactor: 2 });
        await page.goto(renderUrl, { waitUntil: 'networkidle0', timeout: 60000 });
        await page.evaluate(() => document.fonts.ready);
        await new Promise(r => setTimeout(r, INITIAL_WAIT_MS));

        await page.evaluate(() => {
            if (window.Apex) {
                window.Apex.chart = { ...(window.Apex.chart || {}), animations: { enabled: false } };
            }
        });
    }

    const pptx = new PptxGenJS();
    pptx.defineLayout({ name: 'PRESENTATION', width: pptxW, height: pptxH });
    pptx.layout = 'PRESENTATION';

    for (let i = 0; i < slides.length; i++) {
        const s = slides[i];
        const slide = pptx.addSlide();

        slide.background = { fill: hexColor(s.background) };

        if (s.needsScreenshot && page) {
            if (i > 0) {
                await page.evaluate((idx) => {
                    const el = document.querySelector('[x-data]');
                    if (!el) return;
                    const data = window.Alpine && window.Alpine.$data ? window.Alpine.$data(el) : null;
                    if (data && typeof data.goToSlide === 'function') data.goToSlide(idx);
                }, i);
            }

            // Wait until the correct .slide element (by data-slide-index) appears in the DOM.
            // x-if removes/re-inserts elements asynchronously, so we must not rely on a fixed delay.
            try {
                await page.waitForFunction(
                    (idx) => !!document.querySelector(`.slide[data-slide-index="${idx}"]`),
                    { timeout: 5000 },
                    i,
                );
            } catch (_) {
                console.warn(`Slide ${i + 1}: timeout waiting for data-slide-index="${i}", proceeding anyway`);
                await new Promise(r => setTimeout(r, 500));
            }

            const hasChart = await page.evaluate((idx) => {
                const sl = document.querySelector(`.slide[data-slide-index="${idx}"]`) || document.querySelector('.slide');
                return sl ? !!sl.querySelector('.apexcharts-canvas, [id^="chart-pres"]') : false;
            }, i);

            if (hasChart) {
                await new Promise(r => setTimeout(r, CHART_WAIT_MS));
                await page.evaluate(() => {
                    return new Promise(resolve => {
                        const check = () => {
                            const anim = document.querySelector('.apexcharts-canvas.apexcharts-animating');
                            if (!anim) return resolve();
                            setTimeout(check, 100);
                        };
                        check();
                        setTimeout(resolve, 2000);
                    });
                });
            } else {
                await new Promise(r => setTimeout(r, 200));
            }

            // Screenshot the full .slide element to capture all Blade-rendered content.
            // Use data-slide-index to target the correct element after Alpine x-if navigation.
            const slideEl = await page.$(`.slide[data-slide-index="${i}"]`) || await page.$('.slide');
            if (slideEl) {
                const screenshot = await slideEl.screenshot({ type: 'png' });
                const imgData = 'data:image/png;base64,' + screenshot.toString('base64');

                slide.addImage({
                    data: imgData,
                    x: 0,
                    y: 0,
                    w: slideWidth * PX_TO_INCH,
                    h: slideHeight * PX_TO_INCH,
                });
            }

            console.log(`Slide ${i + 1}/${slides.length} screenshot captured (full slide)`);
        }

        // Skip native textboxes for screenshot slides – they are already part of the screenshot
        if (s.needsScreenshot) {
            console.log(`Slide ${i + 1}/${slides.length} built`);
            continue;
        }

        for (const tb of (s.textboxes || [])) {
            const text = stripHtml(tb.text);
            if (!text) continue;

            slide.addText(text, {
                x: tb.x * PX_TO_INCH,
                y: tb.y * PX_TO_INCH,
                w: (tb.width || 400) * PX_TO_INCH,
                h: tb.height ? tb.height * PX_TO_INCH : undefined,
                fontSize: Math.round((tb.fontSize || 16) * 0.75),
                fontFace: fontFamily || 'Arial',
                color: hexColor(tb.color),
                bold: fontWeight(tb.fontWeight),
                align: tb.align || 'left',
                valign: 'top',
                isTextBox: true,
                autoFit: !tb.height,
                wrap: true,
            });
        }

        for (const img of (s.images || [])) {
            if (!img.url) continue;
            try {
                slide.addImage({
                    path: img.url,
                    x: (img.x || 0) * PX_TO_INCH,
                    y: (img.y || 0) * PX_TO_INCH,
                    w: (img.width || 400) * PX_TO_INCH,
                    h: (img.height || 300) * PX_TO_INCH,
                });
            } catch (e) {
                console.error(`Image failed for slide ${i}: ${e.message}`);
            }
        }

        console.log(`Slide ${i + 1}/${slides.length} built`);
    }

    await pptx.writeFile({ fileName: outputPath });
    console.log(`PPTX saved: ${outputPath} (${slides.length} slides)`);

    if (browser) await browser.close();
    process.exit(0);
})().catch(err => {
    console.error('PPTX export failed:', err.message);
    process.exit(1);
});
