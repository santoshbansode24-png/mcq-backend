const os = require('os');
const fs = require('fs');
const path = require('path');

// Get current environment target from CLI arguments
const targetEnv = process.argv[2];
if (targetEnv !== 'local' && targetEnv !== 'railway') {
    console.error('❌ Usage: node switch_env.js [local|railway]');
    process.exit(1);
}

// Automatically detect local machine active IPv4 address
function getLocalIP() {
    const interfaces = os.networkInterfaces();
    
    // Attempt to target active WiFi or Ethernet first
    for (const name of Object.keys(interfaces)) {
        for (const net of interfaces[name]) {
            if (net.family === 'IPv4' && !net.internal) {
                const lowerName = name.toLowerCase();
                if (lowerName.includes('wi-fi') || 
                    lowerName.includes('ethernet') || 
                    lowerName.includes('wlan') || 
                    lowerName.includes('wireless')) {
                    return net.address;
                }
            }
        }
    }
    
    // Fallback to any non-internal IPv4 address
    for (const name of Object.keys(interfaces)) {
        for (const net of interfaces[name]) {
            if (net.family === 'IPv4' && !net.internal) {
                return net.address;
            }
        }
    }
    
    return '127.0.0.1'; // Default localhost fallback
}

const localIP = getLocalIP();
console.log(`🌐 System: Detected active local IPv4 address: ${localIP}`);

const paths = {
    studentApp: path.join(__dirname, 'student_app', 'src', 'api', 'config.js'),
    teacherApp: path.join(__dirname, 'teacher_app', 'src', 'api', 'config.js')
};

function updateConfigFile(filePath, name) {
    if (!fs.existsSync(filePath)) {
        console.warn(`⚠️ Warning: Configuration file for ${name} not found at ${filePath}`);
        return;
    }

    let content = fs.readFileSync(filePath, 'utf8');

    // 1. Dynamic replacement of local SERVER_IP address
    content = content.replace(/SERVER_IP:\s*'[^']*'/g, `SERVER_IP: '${localIP}'`);

    // 2. Toggle active config statement
    if (targetEnv === 'local') {
        // Activate LOCAL_CONFIG, deactivate RAILWAY_CONFIG
        content = content.replace(/\/\/\s*const config\s*=\s*LOCAL_CONFIG;/g, 'const config = LOCAL_CONFIG;');
        content = content.replace(/const config\s*=\s*RAILWAY_CONFIG;/g, '// const config = RAILWAY_CONFIG;');
    } else {
        // Activate RAILWAY_CONFIG, deactivate LOCAL_CONFIG
        content = content.replace(/const config\s*=\s*LOCAL_CONFIG;/g, '// const config = LOCAL_CONFIG;');
        content = content.replace(/\/\/\s*const config\s*=\s*RAILWAY_CONFIG;/g, 'const config = RAILWAY_CONFIG;');
    }

    fs.writeFileSync(filePath, content, 'utf8');
    console.log(`✅ Success: ${name} configuration updated to ${targetEnv.toUpperCase()}.`);
}

// Execute updates
updateConfigFile(paths.studentApp, 'Student App');
updateConfigFile(paths.teacherApp, 'Teacher App');
console.log(`🚀 Environment sync completed successfully!`);
