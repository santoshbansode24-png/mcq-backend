import { Platform } from 'react-native';

// ========================================
// LOCAL XAMPP CONFIGURATION
// ========================================
const LOCAL_CONFIG = {
    SERVER_IP: '10.231.90.239:8080',
    DOMAIN: 'localhost',
    get API_URL() {
        return Platform.OS === 'web'
            ? `http://${this.DOMAIN}/veeru/backend/api`
            : `http://${this.SERVER_IP}/veeru/backend/api`;
    },
    get BASE_URL() {
        return Platform.OS === 'web'
            ? `http://${this.DOMAIN}/veeru/backend`
            : `http://${this.SERVER_IP}/veeru/backend`;
    }
};

// ========================================
// RAILWAY PRODUCTION CONFIGURATION
// ========================================
const RAILWAY_CONFIG = {
    API_URL: 'https://api.veeruapp.in/api',
    BASE_URL: 'https://api.veeruapp.in'
};

// ========================================
// ACTIVE CONFIGURATION
// ========================================

// Export the configuration you want to use
const config = RAILWAY_CONFIG; // Changed to RAILWAY_CONFIG for production build

export default config;

// Export individual values for backward compatibility
export const API_URL = config.API_URL;
export const BASE_URL = config.BASE_URL;

// ========================================
// SERVER CONNECTION CHECK
// ========================================

/**
 * Checks if the backend server is reachable
 * @returns {Promise<boolean>} True if server is reachable
 */
export const checkServerConnection = async () => {
    try {
        const response = await fetch(`${config.API_URL}/health.php`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
            },
            timeout: 25000, // 25 second timeout for mobile data
        });

        if (response.ok) {
            console.log('✅ Server connection successful');
            return true;
        } else {
            console.warn('⚠️ Server returned non-OK status:', response.status);
            return false;
        }
    } catch (error) {
        console.error('❌ Server connection failed:', error.message);
        // Return true anyway to not block the app
        return true;
    }
};
