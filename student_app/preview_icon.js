const { Jimp } = require('jimp');
const path = require('path');

const ASSETS = path.join(__dirname, 'assets');
const INPUT = path.join(ASSETS, 'adaptive-foreground.png');
const OUTPUT = path.join(__dirname, 'icon_preview.png');

async function createPreview() {
    console.log('Generating circle-cropped preview...');
    const img = await Jimp.read(INPUT);
    const size = img.bitmap.width;

    // Create a grey background
    const final = new Jimp({ width: size, height: size, color: 0xCCCCCCFF });

    // Safe circle diameter is 66% (some say 72%)
    const centerX = size / 2;
    const centerY = size / 2;
    const radius = size * 0.33; // 66% diameter

    // Manually alpha-mask the image into a circle
    const masked = img.clone();
    for (let y = 0; y < size; y++) {
        for (let x = 0; x < size; x++) {
            const idx = (size * y + x) * 4;
            const dist = Math.sqrt(Math.pow(x - centerX, 2) + Math.pow(y - centerY, 2));
            if (dist > radius) {
                masked.bitmap.data[idx + 3] = 0; // Transparent
            }
        }
    }

    final.composite(masked, 0, 0);
    await final.write(OUTPUT);
    console.log('Preview saved to icon_preview.png');
}

createPreview().catch(console.error);
