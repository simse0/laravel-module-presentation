#!/usr/bin/env node

/**
 * Presentation PDF Export via Headless Chrome
 *
 * Takes screenshots of each slide using Puppeteer and combines them into a PDF.
 * Called by PdfExportService.php – not intended for direct use.
 *
 * Usage: node export-pdf.js <url> <output-path> <slide-count> [slide-width] [slide-height] [chrome-path]
 */

const puppeteer = require('puppeteer');
const { PDFDocument } = require('pdf-lib');
const { writeFileSync } = require('fs');

const [,, url, outputPath, slideCountStr, slideWidthStr, slideHeightStr, chromePath] = process.argv;

const slideCount = parseInt(slideCountStr);
const slideWidth = parseInt(slideWidthStr) || 1280;
const slideHeight = parseInt(slideHeightStr) || 720;

if (!url || !outputPath || !slideCount) {
    console.error('Usage: export-pdf.js <url> <output-path> <slide-count> [slide-width] [slide-height] [chrome-path]');
    process.exit(1);
}

const CHART_WAIT_MS = 800;
const NO_CHART_WAIT_MS = 150;
const INITIAL_WAIT_MS = 1500;

(async () => {
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

    if (chromePath) {
        launchOptions.executablePath = chromePath;
    }

    const browser = await puppeteer.launch(launchOptions);
    const page = await browser.newPage();

    await page.setViewport({ width: 1920, height: 1080, deviceScaleFactor: 1 });

    await page.goto(url, { waitUntil: 'networkidle0', timeout: 60000 });

    await page.evaluate(() => document.fonts.ready);
    await new Promise(r => setTimeout(r, INITIAL_WAIT_MS));

    await page.evaluate(() => {
        if (window.Apex) {
            window.Apex.chart = { ...(window.Apex.chart || {}), animations: { enabled: false } };
        }
    });

    const screenshots = [];

    for (let i = 0; i < slideCount; i++) {
        if (i > 0) {
            await page.evaluate((idx) => {
                const el = document.querySelector('[x-data]');
                if (!el) return;
                const data = window.Alpine && window.Alpine.$data ? window.Alpine.$data(el) : null;
                if (!data) return;
                if (typeof data.goToSlide === 'function') {
                    data.goToSlide(idx);
                }
            }, i);
        }

        const hasChart = await page.evaluate(() => {
            const slide = document.querySelector('.slide');
            return slide ? !!slide.querySelector('.apexcharts-canvas, [id^="chart-pres"]') : false;
        });

        if (hasChart) {
            await new Promise(r => setTimeout(r, CHART_WAIT_MS));

            await page.evaluate(() => {
                return new Promise(resolve => {
                    const check = () => {
                        const animating = document.querySelector('.apexcharts-canvas.apexcharts-animating');
                        if (!animating) return resolve();
                        setTimeout(check, 100);
                    };
                    check();
                    setTimeout(resolve, 2000);
                });
            });
        } else if (i > 0) {
            await new Promise(r => setTimeout(r, NO_CHART_WAIT_MS));
        }

        const slideEl = await page.$('.slide');
        if (!slideEl) {
            console.error(`Slide ${i}: .slide element not found, skipping`);
            continue;
        }

        const screenshot = await slideEl.screenshot({ type: 'png' });
        screenshots.push(screenshot);
        console.log(`Slide ${i + 1}/${slideCount} captured${hasChart ? ' (chart)' : ''}`);
    }

    if (screenshots.length === 0) {
        throw new Error('No slides captured');
    }

    const pdfDoc = await PDFDocument.create();

    for (const screenshotBuffer of screenshots) {
        const img = await pdfDoc.embedPng(screenshotBuffer);
        const pdfPage = pdfDoc.addPage([slideWidth, slideHeight]);
        pdfPage.drawImage(img, {
            x: 0,
            y: 0,
            width: slideWidth,
            height: slideHeight,
        });
    }

    const pdfBytes = await pdfDoc.save();
    writeFileSync(outputPath, pdfBytes);

    console.log(`PDF saved: ${outputPath} (${screenshots.length} slides)`);

    await browser.close();
    process.exit(0);
})().catch(err => {
    console.error('Export failed:', err.message);
    process.exit(1);
});
