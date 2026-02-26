const { withAndroidColors, AndroidConfig } = require('@expo/config-plugins');

module.exports = function withUcropColors(config) {
    return withAndroidColors(config, async (config) => {
        config.modResults = AndroidConfig.Colors.assignColorValue(config.modResults, { name: 'ucrop_color_toolbar', value: '#1E293B' });
        config.modResults = AndroidConfig.Colors.assignColorValue(config.modResults, { name: 'ucrop_color_statusbar', value: '#0F172A' });
        config.modResults = AndroidConfig.Colors.assignColorValue(config.modResults, { name: 'ucrop_color_toolbar_widget', value: '#FFFFFF' });
        config.modResults = AndroidConfig.Colors.assignColorValue(config.modResults, { name: 'ucrop_color_widget', value: '#CBD5E1' });
        config.modResults = AndroidConfig.Colors.assignColorValue(config.modResults, { name: 'ucrop_color_widget_active', value: '#FBBF24' });
        config.modResults = AndroidConfig.Colors.assignColorValue(config.modResults, { name: 'ucrop_color_widget_background', value: '#1E293B' });
        config.modResults = AndroidConfig.Colors.assignColorValue(config.modResults, { name: 'ucrop_color_crop_background', value: '#000000' });
        return config;
    });
};
