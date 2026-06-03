import fs from "node:fs/promises";
import path from "node:path";
import { createCanvas, DOMMatrix, ImageData, Path2D } from "@napi-rs/canvas";
import sharp from "sharp";
import jsQR from "jsqr";

globalThis.DOMMatrix ??= DOMMatrix;
globalThis.ImageData ??= ImageData;
globalThis.Path2D ??= Path2D;

const { getDocument } = await import("pdfjs-dist/legacy/build/pdf.mjs");

const [pdfPath, outputDir, questionCountArg = "50"] = process.argv.slice(2);

if (!pdfPath || !outputDir) {
    throw new Error("Pemakaian: node baca-ljk-pdf.mjs <file-pdf> <direktori-output>");
}

const TARGET_WIDTH = 1485;
const TARGET_HEIGHT = 2100;
const SHEET_WIDTH = 2970;
const GRID_TOP = 610;
const GRID_LEFT = 94;
const GRID_GAP = 48;
const ROW_HEIGHT = 46.5;
const QUESTION_WIDTH = 70;
const BUBBLE_RADIUS = 9;
const DARK_THRESHOLD = 145;
const FILLED_RATIO = 0.34;
const AMBIGUOUS_GAP = 0.12;
const ANSWER_OPTIONS = ["A", "B", "C", "D"];
const QUESTION_COUNT = Math.max(1, Math.min(50, Number.parseInt(questionCountArg, 10) || 50));

await fs.mkdir(outputDir, { recursive: true });

const pdfBytes = new Uint8Array(await fs.readFile(pdfPath));
const document = await getDocument({ data: pdfBytes, disableWorker: true }).promise;
const results = [];

for (let pageNumber = 1; pageNumber <= document.numPages; pageNumber++) {
    const page = await document.getPage(pageNumber);
    const initialViewport = page.getViewport({ scale: 1 });
    const landscape = initialViewport.width > initialViewport.height;
    const desiredWidth = landscape ? SHEET_WIDTH : TARGET_WIDTH;
    const viewport = page.getViewport({ scale: desiredWidth / initialViewport.width });
    const canvas = createCanvas(Math.ceil(viewport.width), Math.ceil(viewport.height));
    const context = canvas.getContext("2d");

    await page.render({ canvasContext: context, viewport }).promise;

    const renderedPage = await sharp(canvas.toBuffer("image/png"))
        .resize({
            width: landscape ? SHEET_WIDTH : TARGET_WIDTH,
            height: TARGET_HEIGHT,
            fit: "fill",
        })
        .png()
        .toBuffer();
    const regions = landscape
        ? [
            { slot: 1, buffer: await sharp(renderedPage).extract({ left: 0, top: 0, width: TARGET_WIDTH, height: TARGET_HEIGHT }).toBuffer() },
            { slot: 2, buffer: await sharp(renderedPage).extract({ left: TARGET_WIDTH, top: 0, width: TARGET_WIDTH, height: TARGET_HEIGHT }).toBuffer() },
        ]
        : [{ slot: 1, buffer: renderedPage }];

    for (const region of regions) {
        const previewName = `halaman-${String(pageNumber).padStart(3, "0")}-ljk-${region.slot}.jpg`;
        const previewPath = path.join(outputDir, previewName);
        const normalized = await sharp(region.buffer)
            .resize({ width: TARGET_WIDTH, height: TARGET_HEIGHT, fit: "fill" })
            .jpeg({ quality: 88 })
            .toBuffer();

        await fs.writeFile(previewPath, normalized);

        const markerResult = await detectMarkers(normalized);
        const token = await decodeToken(normalized);
        const darkRatio = await calculateDarkRatio(normalized);

        if (!token && markerResult.count === 0 && darkRatio < 0.01) {
            continue;
        }

        const answers = await readAnswers(normalized);
        const warnings = [];

        if (!markerResult.ok) {
            warnings.push("Marker sudut belum terbaca lengkap. Periksa posisi dan kualitas hasil scan.");
        }

        if (!token) {
            warnings.push("QR token belum terbaca.");
        }

        if (answers.some((answer) => answer.status !== "terbaca")) {
            warnings.push("Ada jawaban kosong atau ganda yang perlu diperiksa.");
        }

        results.push({
            page: pageNumber,
            slot: region.slot,
            token,
            preview: previewName,
            markers_ok: markerResult.ok,
            marker_count: markerResult.count,
            dark_ratio: Number(darkRatio.toFixed(4)),
            status: warnings.length ? "perlu_diperiksa" : "terbaca",
            warnings,
            answers,
        });
    }
}

process.stdout.write(JSON.stringify({
    pages: document.numPages,
    sheets: results,
}));

async function calculateDarkRatio(image) {
    const { data } = await sharp(image)
        .resize({ width: 150, height: 212, fit: "fill" })
        .greyscale()
        .raw()
        .toBuffer({ resolveWithObject: true });
    let darkPixels = 0;

    for (const value of data) {
        if (value < 190) {
            darkPixels++;
        }
    }

    return darkPixels / data.length;
}

async function detectMarkers(image) {
    const { data, info } = await sharp(image)
        .greyscale()
        .raw()
        .toBuffer({ resolveWithObject: true });
    const zones = [
        [20, 20, 130, 130],
        [info.width - 150, 20, 130, 130],
        [20, info.height - 150, 130, 130],
        [info.width - 150, info.height - 150, 130, 130],
    ];
    const count = zones.filter(([left, top, width, height]) => {
        let darkPixels = 0;

        for (let y = top; y < top + height; y++) {
            for (let x = left; x < left + width; x++) {
                if (data[y * info.width + x] < 80) {
                    darkPixels++;
                }
            }
        }

        return darkPixels / (width * height) > 0.06;
    }).length;

    return { ok: count === 4, count };
}

async function decodeToken(image) {
    const { data, info } = await sharp(image)
        .extract({ left: 1040, top: 35, width: 405, height: 405 })
        .ensureAlpha()
        .raw()
        .toBuffer({ resolveWithObject: true });
    const qr = jsQR(new Uint8ClampedArray(data), info.width, info.height, {
        inversionAttempts: "attemptBoth",
    });

    return qr?.data && /^[0-9]{18}$/.test(qr.data) ? qr.data : null;
}

async function readAnswers(image) {
    const { data, info } = await sharp(image)
        .greyscale()
        .raw()
        .toBuffer({ resolveWithObject: true });
    const columnWidth = (TARGET_WIDTH - (2 * GRID_LEFT) - GRID_GAP) / 2;
    const answerWidth = (columnWidth - QUESTION_WIDTH) / 4;
    const answers = [];

    for (let question = 1; question <= QUESTION_COUNT; question++) {
        const column = question > 25 ? 1 : 0;
        const row = (question - 1) % 25;
        const startX = GRID_LEFT + (column * (columnWidth + GRID_GAP)) + QUESTION_WIDTH;
        const centerY = GRID_TOP + (row * ROW_HEIGHT) + (ROW_HEIGHT / 2);
        const darkness = {};

        for (let optionIndex = 0; optionIndex < ANSWER_OPTIONS.length; optionIndex++) {
            const centerX = startX + (optionIndex * answerWidth) + 16;
            darkness[ANSWER_OPTIONS[optionIndex]] = sampleDarkness(data, info.width, info.height, centerX, centerY);
        }

        const sorted = Object.entries(darkness).sort((left, right) => right[1] - left[1]);
        const [bestOption, bestRatio] = sorted[0];
        const secondRatio = sorted[1][1];
        let status = "kosong";
        let answer = null;

        if (bestRatio >= FILLED_RATIO && bestRatio - secondRatio >= AMBIGUOUS_GAP) {
            status = "terbaca";
            answer = bestOption;
        } else if (bestRatio >= FILLED_RATIO) {
            status = "ganda";
        }

        answers.push({
            number: question,
            answer,
            status,
            darkness: Object.fromEntries(Object.entries(darkness).map(([key, value]) => [key, Number(value.toFixed(4))])),
        });
    }

    return answers;
}

function sampleDarkness(data, width, height, centerX, centerY) {
    let dark = 0;
    let sampled = 0;

    for (let y = Math.floor(centerY - BUBBLE_RADIUS); y <= Math.ceil(centerY + BUBBLE_RADIUS); y++) {
        for (let x = Math.floor(centerX - BUBBLE_RADIUS); x <= Math.ceil(centerX + BUBBLE_RADIUS); x++) {
            const dx = x - centerX;
            const dy = y - centerY;

            if (x < 0 || y < 0 || x >= width || y >= height || (dx * dx) + (dy * dy) > BUBBLE_RADIUS * BUBBLE_RADIUS) {
                continue;
            }

            sampled++;

            if (data[y * width + x] < DARK_THRESHOLD) {
                dark++;
            }
        }
    }

    return sampled ? dark / sampled : 0;
}
