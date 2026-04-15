import { Dimensions } from 'react-native';

const { width, height } = Dimensions.get('window');

// Guideline sizes are based on standard ~5" screen mobile device (e.g. iPhone X baseline)
const guidelineBaseWidth = 375;
const guidelineBaseHeight = 812;

/**
 * Scale relative to width
 * Best for width, margin, padding, fontSize
 */
const scale = (size) => (width / guidelineBaseWidth) * size;

/**
 * Scale relative to height
 * Best for height, marginTop/Bottom
 */
const verticalScale = (size) => (height / guidelineBaseHeight) * size;

/**
 * Moderate scale relative to width
 * Scales standardly but limits how large it gets (so it doesn't look absurd on iPads)
 */
const moderateScale = (size, factor = 0.5) => size + (scale(size) - size) * factor;

export { 
    scale, 
    verticalScale, 
    moderateScale, 
    moderateScale as rs, // Short alias
    verticalScale as rsv,
    width as screenWidth, 
    height as screenHeight 
};
