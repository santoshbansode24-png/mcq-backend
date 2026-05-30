export const lightTheme = {
    mode: 'light',

    // Fonts
    fontRegular: 'NotoSans-Regular',
    fontBold: 'NotoSans-Bold',

    // Main backgrounds with subtle gradients
    background: '#F8FAFC', // Crisp Slate 50
    backgroundGradientStart: '#F1F5F9', // Slate 100
    backgroundGradientEnd: '#E2E8F0', // Slate 200

    card: '#FFFFFF',
    cardShadow: 'rgba(30, 41, 59, 0.08)', // Soft slate shadow

    // Text colors
    text: '#0F172A', // Slate 900
    textSecondary: '#475569', // Slate 600

    // Primary colors - Professional Blue
    primary: '#2563EB', // Blue 600
    primaryLight: '#3B82F6', // Blue 500
    primaryDark: '#1D4ED8', // Blue 700
    primaryGradientStart: '#3B82F6',
    primaryGradientEnd: '#1E40AF', // Blue 800

    // Secondary accent
    secondary: '#0EA5E9', // Sky Blue 500
    secondaryLight: '#38BDF8',
    secondaryDark: '#0369A1',

    // Borders
    border: '#E2E8F0', // Slate 200
    borderActive: '#2563EB', // Blue 600

    // Tab bar
    tabBar: '#FFFFFF',
    tabBarGradientStart: '#FFFFFF',
    tabBarGradientEnd: '#F8FAFC',
    tabBarShadow: 'rgba(15, 23, 42, 0.05)',

    tabIcon: '#94A3B8', // Slate 400
    tabIconActive: '#2563EB', // Blue 600
    tabIconGlow: 'rgba(37, 99, 235, 0.2)', // Soft blue glow

    // Tab background
    tabTileInactive: 'rgba(241, 245, 249, 1)',
    tabTileActive: 'linear-gradient(135deg, #3B82F6 0%, #2563EB 100%)',
    tabTileGlowStart: '#3B82F6',
    tabTileGlowEnd: '#1D4ED8',

    // Status colors
    success: '#10B981', // Emerald 500
    successGradient: 'linear-gradient(135deg, #34D399 0%, #059669 100%)',

    error: '#EF4444', // Red 500
    errorGradient: 'linear-gradient(135deg, #F87171 0%, #DC2626 100%)',

    warning: '#F59E0B', // Amber 500
    warningGradient: 'linear-gradient(135deg, #FBBF24 0%, #D97706 100%)',

    info: '#0EA5E9', // Sky Blue 500
    infoGradient: 'linear-gradient(135deg, #38BDF8 0%, #0284C7 100%)',

    // Additional accents
    accent1: '#3B82F6', // Blue 500
    accent2: '#0EA5E9', // Sky Blue 500
    accent3: '#8B5CF6', // Violet 500
    accent4: '#10B981', // Emerald 500
    accent5: '#F59E0B', // Amber 500
};

export const darkTheme = {
    mode: 'dark',

    // Fonts
    fontRegular: 'NotoSans-Regular',
    fontBold: 'NotoSans-Bold',

    // Main backgrounds - Deep Blue/Slate
    background: '#0F172A', // Slate 900
    backgroundGradientStart: '#0B1120', // Slate 950
    backgroundGradientEnd: '#1E293B', // Slate 800

    card: 'rgba(30, 41, 59, 0.7)', // Translucent Slate 800 for Glassmorphism
    cardShadow: 'rgba(0, 0, 0, 0.5)',

    // Text colors
    text: '#F8FAFC', // Slate 50
    textSecondary: '#94A3B8', // Slate 400

    // Primary colors - Deep Blue Glassmorphism
    primary: '#1A237E', // Indigo 900
    primaryLight: '#3F51B5', // Indigo 500
    primaryDark: '#000051', // Dark Indigo
    primaryGradientStart: '#1A237E',
    primaryGradientEnd: '#000051',

    // Secondary accent
    secondary: '#00BCD4', // Cyan
    secondaryLight: '#00E5FF',
    secondaryDark: '#008BA3',

    // Borders
    border: 'rgba(148, 163, 184, 0.1)', // Subtle light border for glass
    borderActive: '#3F51B5', // Indigo 500

    // Tab bar - Glassmorphism design
    tabBar: 'rgba(15, 23, 42, 0.85)',
    tabBarGradientStart: 'rgba(15, 23, 42, 0.9)',
    tabBarGradientEnd: 'rgba(30, 41, 59, 0.9)',
    tabBarShadow: 'rgba(0, 0, 0, 0.5)',

    tabIcon: '#64748B', // Slate 500
    tabIconActive: '#3F51B5', // Indigo 500
    tabIconGlow: 'rgba(63, 81, 181, 0.4)', // Indigo glow

    // Tab background - Glassmorphism tiles
    tabTileInactive: 'rgba(30, 41, 59, 0.4)',
    tabTileActive: 'linear-gradient(135deg, rgba(63, 81, 181, 0.8) 0%, rgba(26, 35, 126, 0.8) 100%)',
    tabTileGlowStart: '#3F51B5',
    tabTileGlowEnd: '#1A237E',

    // Status colors
    success: '#10B981', // Emerald 500
    successGradient: 'linear-gradient(135deg, #059669 0%, #047857 100%)',

    error: '#EF4444', // Red 500
    errorGradient: 'linear-gradient(135deg, #DC2626 0%, #B91C1C 100%)',

    warning: '#F59E0B', // Amber 500
    warningGradient: 'linear-gradient(135deg, #D97706 0%, #B45309 100%)',

    info: '#0EA5E9', // Sky Blue 500
    infoGradient: 'linear-gradient(135deg, #0284C7 0%, #0369A1 100%)',

    // Additional accents
    accent1: '#3F51B5', // Indigo 500
    accent2: '#00BCD4', // Cyan
    accent3: '#10B981', // Emerald
    accent4: '#8B5CF6', // Violet
    accent5: '#F59E0B', // Amber
};

// Optional: Export gradient utilities
export const gradients = {
    light: {
        primary: 'linear-gradient(135deg, #FF6B9D 0%, #C70039 100%)',
        secondary: 'linear-gradient(135deg, #00D9FF 0%, #00A8CC 100%)',
        rainbow: 'linear-gradient(90deg, #FF6B9D 0%, #FEC84E 33%, #00F260 66%, #00D9FF 100%)',
        sunset: 'linear-gradient(135deg, #FF0844 0%, #FFB199 50%, #FEC84E 100%)',
        ocean: 'linear-gradient(135deg, #667EEA 0%, #00D9FF 100%)',
    },
    dark: {
        primary: 'linear-gradient(135deg, #FF2E63 0%, #FE6B8B 100%)',
        secondary: 'linear-gradient(135deg, #08FFC8 0%, #00D9A3 100%)',
        rainbow: 'linear-gradient(90deg, #FF2E63 0%, #B24BF3 25%, #08FFC8 50%, #00F5FF 75%, #FFD93D 100%)',
        neon: 'linear-gradient(135deg, #FF2E63 0%, #B24BF3 50%, #08FFC8 100%)',
        cosmic: 'linear-gradient(135deg, #6B73FF 0%, #000DFF 100%)',
    }
};

// Shadow presets for glowing effects
export const shadows = {
    light: {
        small: '0 2px 8px rgba(233, 69, 96, 0.15)',
        medium: '0 4px 16px rgba(233, 69, 96, 0.2)',
        large: '0 8px 32px rgba(233, 69, 96, 0.25)',
        glow: '0 0 20px rgba(255, 107, 157, 0.4)',
    },
    dark: {
        small: '0 2px 8px rgba(255, 46, 99, 0.3)',
        medium: '0 4px 16px rgba(255, 46, 99, 0.4)',
        large: '0 8px 32px rgba(255, 46, 99, 0.5)',
        glow: '0 0 30px rgba(255, 46, 99, 0.6), 0 0 60px rgba(8, 255, 200, 0.3)',
    }
};