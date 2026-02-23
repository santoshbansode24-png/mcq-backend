const { Jimp } = require('jimp');
const path = require('path');

const ASSETS = path.join(__dirname, 'assets');
const INPUT = path.join(ASSETS, 'icon.png');

function autoCrop(img) {
    const w = img.bitmap.width;
    const h = img.bitmap.height;
    let minX = w, minY = h, maxX = 0, maxY = 0;

    // Find bounding box of non-white pixels
    for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
            const idx = (w * y + x) * 4;
            const r = img.bitmap.data[idx];
            const g = img.bitmap.data[idx + 1];
            const b = img.bitmap.data[idx + 2];
            const a = img.bitmap.data[idx + 3];
            // If pixel is NOT white/transparent
            if (a > 20 && !(r > 240 && g > 240 && b > 240)) {
                if (x < minX) minX = x;
                if (y < minY) minY = y;
                if (x > maxX) maxX = x;
                if (y > maxY) maxY = y;
            }
        }
    }

    console.log(`Logo bounding box: (${minX},${minY}) -> (${maxX},${maxY})`);
    const cropW = maxX - minX + 1;
    const cropH = maxY - minY + 1;
    img.crop({ x: minX, y: minY, w: cropW, h: cropH });
    return img;
}

async function makeIcon(croppedLogo, canvasSize, fillPercent, outputPath) {
    const logoSize = Math.round(canvasSize * fillPercent);

    // Clone and resize the logo keeping aspect ratio
    const resized = croppedLogo.clone();
    resized.contain({ w: logoSize, h: logoSize });

    // Create white background canvas
    const canvas = new Jimp({ width: canvasSize, height: canvasSize, color: 0xFFFFFFFF });

    // Center the logo on the canvas
    const x = Math.round((canvasSize - resized.bitmap.width) / 2);
    const y = Math.round((canvasSize - resized.bitmap.height) / 2);

    canvas.composite(resized, x, y);
    await canvas.write(outputPath);
    console.log(`✅ Saved: ${outputPath}`);
}

(async () => {
    console.log('📂 Loading icon.png...');
    const original = await Jimp.read(INPUT);

    console.log('✂️  Auto-cropping white space...');
    const cropped = autoCrop(original);

    // icon.png — logo fills 82% (for iOS & Play Store listing)
    console.log('\n🖼️  Creating icon.png (1024x1024, logo at 82%)...');
    await makeIcon(cropped, 1024, 0.82, path.join(ASSETS, 'icon.png'));

    // adaptive-foreground.png — logo fills 65% only
    // Android safe zone = central 72% (25% cropped from each edge in circle mode)
    // 65% ensures logo is big but NEVER gets clipped on any device
    console.log('\n📱 Creating adaptive-foreground.png (1024x1024, logo at 65%)...');
    await makeIcon(cropped, 1024, 0.65, path.join(ASSETS, 'adaptive-foreground.png'));

    console.log('\n🎉 Done! Both icon files have been fixed.');
    console.log('   Rebuild your APK to see the new icon on your phone.');
})();
