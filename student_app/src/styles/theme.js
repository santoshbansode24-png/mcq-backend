export const lightTheme = {
    mode: 'light',

    // Fonts
    fontRegular: 'NotoSans-Regular',
    fontBold: 'NotoSans-Bold',


    // Main backgrounds with subtle gradients
    background: '#F0F8FF', // Crisp Alice Blue
    backgroundGradientStart: '#FFE5F1', // Soft Pink
    backgroundGradientEnd: '#E0F4FF', // Soft Sky Blue

    card: '#FFFFFF',
    cardShadow: 'rgba(255, 107, 234, 0.15)', // Pink-tinted shadow

    // Text colors
    text: '#1A0B2E', // Rich Deep Purple-Black
    textSecondary: '#5F4B8B',

    // Primary colors - Radiant Purple/Pink
    primary: '#E94560', // Vibrant Hot Pink
    primaryLight: '#FF6B9D', // Lighter Pink
    primaryDark: '#C70039', // Deep Pink-Red
    primaryGradientStart: '#FF6B9D',
    primaryGradientEnd: '#C70039',

    // Secondary accent
    secondary: '#00D9FF', // Electric Cyan
    secondaryLight: '#5DFDFF',
    secondaryDark: '#00A8CC',

    // Borders with glow effect
    border: '#FFD6E8', // Soft Pink border
    borderActive: '#FF6B9D', // Vibrant border on focus

    // Tab bar - Radiant design
    tabBar: '#FFFFFF',
    tabBarGradientStart: '#FFF5F8',
    tabBarGradientEnd: '#F0F8FF',
    tabBarShadow: 'rgba(233, 69, 96, 0.2)',

    tabIcon: '#A78BFA', // Soft Purple
    tabIconActive: '#E94560', // Hot Pink when active
    tabIconGlow: 'rgba(233, 69, 96, 0.3)', // Glow effect

    // Tab background - Radiant gradient tiles
    tabTileInactive: 'rgba(167, 139, 250, 0.15)', // Soft purple tint
    tabTileActive: 'linear-gradient(135deg, #FF6B9D 0%, #FEC84E 50%, #00D9FF 100%)', // Rainbow gradient
    tabTileGlowStart: '#FF6B9D',
    tabTileGlowEnd: '#FEC84E',

    // Status colors - Ultra vibrant
    success: '#00F260', // Electric Green
    successGradient: 'linear-gradient(135deg, #0575E6 0%, #00F260 100%)',

    error: '#FF0844', // Neon Red
    errorGradient: 'linear-gradient(135deg, #FF0844 0%, #FFB199 100%)',

    warning: '#FFB800', // Vibrant Golden Yellow
    warningGradient: 'linear-gradient(135deg, #FFB800 0%, #FF8A00 100%)',

    info: '#00D9FF', // Electric Blue
    infoGradient: 'linear-gradient(135deg, #667EEA 0%, #00D9FF 100%)',

    // Additional vibrant accents
    accent1: '#FF6B9D', // Hot Pink
    accent2: '#FEC84E', // Bright Yellow
    accent3: '#00F5FF', // Cyan Flash
    accent4: '#B24BF3', // Vivid Purple
    accent5: '#00E5A0', // Mint Green
};

export const darkTheme = {
    mode: 'dark',

    // Fonts
    fontRegular: 'NotoSans-Regular',
    fontBold: 'NotoSans-Bold',


    // Main backgrounds - Deep cosmic colors
    background: '#0A0E27', // Deep Space Blue
    backgroundGradientStart: '#1A1B3D', // Dark Purple
    backgroundGradientEnd: '#0D1B2A', // Midnight Blue

    card: '#1E1E3F', // Rich Dark Purple
    cardShadow: 'rgba(255, 107, 234, 0.25)',

    // Text colors
    text: '#FFFFFF',
    textSecondary: '#C5B3E6',

    // Primary colors - Neon Purple/Pink
    primary: '#FF2E63', // Neon Pink
    primaryLight: '#FF6B9D',
    primaryDark: '#C70039',
    primaryGradientStart: '#FF2E63',
    primaryGradientEnd: '#FE6B8B',

    // Secondary accent
    secondary: '#08FFC8', // Neon Cyan
    secondaryLight: '#5DFDFF',
    secondaryDark: '#00D9A3',

    // Borders with neon glow
    border: '#3D3560', // Dark Purple border
    borderActive: '#FF2E63', // Neon border on focus

    // Tab bar - Glowing neon design
    tabBar: '#1A1B3F',
    tabBarGradientStart: '#1E1E3F',
    tabBarGradientEnd: '#2A2550',
    tabBarShadow: 'rgba(255, 46, 99, 0.3)',

    tabIcon: '#A78BFA', // Soft Purple
    tabIconActive: '#FF2E63', // Neon Pink when active
    tabIconGlow: 'rgba(255, 46, 99, 0.5)', // Strong glow effect

    // Tab background - Radiant neon gradient tiles
    tabTileInactive: 'rgba(167, 139, 250, 0.2)',
    tabTileActive: 'linear-gradient(135deg, #FF2E63 0%, #FE6B8B 25%, #B24BF3 50%, #08FFC8 75%, #00F5FF 100%)', // Neon rainbow
    tabTileGlowStart: '#FF2E63',
    tabTileGlowEnd: '#08FFC8',

    // Status colors - Neon vibrant
    success: '#00F260', // Neon Green
    successGradient: 'linear-gradient(135deg, #0575E6 0%, #00F260 100%)',

    error: '#FF2E63', // Neon Red
    errorGradient: 'linear-gradient(135deg, #FF2E63 0%, #FE6B8B 100%)',

    warning: '#FFD93D', // Neon Yellow
    warningGradient: 'linear-gradient(135deg, #FFD93D 0%, #FF8A00 100%)',

    info: '#08FFC8', // Neon Cyan
    infoGradient: 'linear-gradient(135deg, #6B73FF 0%, #08FFC8 100%)',

    // Additional neon accents
    accent1: '#FF2E63', // Neon Pink
    accent2: '#FFD93D', // Neon Yellow
    accent3: '#08FFC8', // Neon Cyan
    accent4: '#B24BF3', // Neon Purple
    accent5: '#00F5FF', // Electric Blue
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