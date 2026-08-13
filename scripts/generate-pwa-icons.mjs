import fs from 'node:fs/promises';
import sharp from 'sharp';

const sumberLogo = 'public/images/logo-nusa.png';
const direktoriTujuan = 'public/images/pwa';

await fs.mkdir(direktoriTujuan, { recursive: true });

async function buatIkon(ukuran, namaFile, maskable = false) {
    const ukuranPanel = Math.round(ukuran * (maskable ? 0.68 : 0.86));
    const ukuranLogo = Math.round(ukuran * (maskable ? 0.50 : 0.67));
    const posisiPanel = Math.round((ukuran - ukuranPanel) / 2);
    const radiusPanel = maskable ? Math.round(ukuranPanel / 2) : Math.round(ukuran * 0.14);
    const latar = Buffer.from(`
        <svg width="${ukuran}" height="${ukuran}" xmlns="http://www.w3.org/2000/svg">
            <rect width="${ukuran}" height="${ukuran}" fill="#15477A"/>
            <rect
                x="${posisiPanel}"
                y="${posisiPanel}"
                width="${ukuranPanel}"
                height="${ukuranPanel}"
                rx="${radiusPanel}"
                fill="#ffffff"
            />
        </svg>
    `);
    const logo = await sharp(sumberLogo)
        .resize(ukuranLogo, ukuranLogo, {
            fit: 'contain',
            background: { r: 0, g: 0, b: 0, alpha: 0 },
        })
        .png()
        .toBuffer();

    await sharp(latar)
        .composite([{
            input: logo,
            left: Math.round((ukuran - ukuranLogo) / 2),
            top: Math.round((ukuran - ukuranLogo) / 2),
        }])
        .png()
        .toFile(`${direktoriTujuan}/${namaFile}`);
}

await buatIkon(180, 'icon-180.png');
await buatIkon(192, 'icon-192.png');
await buatIkon(512, 'icon-512.png');
await buatIkon(512, 'icon-maskable-512.png', true);

console.log('Ikon PWA NUSA berhasil dibuat.');
