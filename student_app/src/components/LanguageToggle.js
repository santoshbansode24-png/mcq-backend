import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';

/**
 * Language Toggle Component for Scholarship & Olympiad Board
 * Displays a segmented control to switch between English and Marathi
 */
const LanguageToggle = ({
    selectedLanguage = 'english',
    onLanguageChange,
    englishCount = null,
    marathiCount = null,
    style
}) => {
    const handlePress = (lang) => {
        if (onLanguageChange && lang !== selectedLanguage) {
            onLanguageChange(lang);
        }
    };

    return (
        <View style={[styles.container, style]}>
            <TouchableOpacity
                style={[
                    styles.button,
                    styles.leftButton,
                    selectedLanguage === 'english' && styles.activeButton
                ]}
                onPress={() => handlePress('english')}
                disabled={englishCount === 0}
            >
                <Text style={[
                    styles.buttonText,
                    selectedLanguage === 'english' && styles.activeButtonText,
                    englishCount === 0 && styles.disabledText
                ]}>
                    EN
                    {englishCount !== null && ` (${englishCount})`}
                </Text>
            </TouchableOpacity>

            <TouchableOpacity
                style={[
                    styles.button,
                    styles.rightButton,
                    selectedLanguage === 'marathi' && styles.activeButton
                ]}
                onPress={() => handlePress('marathi')}
                disabled={marathiCount === 0}
            >
                <Text style={[
                    styles.buttonText,
                    selectedLanguage === 'marathi' && styles.activeButtonText,
                    marathiCount === 0 && styles.disabledText
                ]}>
                    MR
                    {marathiCount !== null && ` (${marathiCount})`}
                </Text>
            </TouchableOpacity>
        </View>
    );
};

const styles = StyleSheet.create({
    container: {
        flexDirection: 'row',
        backgroundColor: 'rgba(255,255,255,0.2)',
        borderRadius: 8,
        padding: 2,
        overflow: 'hidden',
    },
    button: {
        paddingVertical: 6,
        paddingHorizontal: 12,
        justifyContent: 'center',
        alignItems: 'center',
        minWidth: 50,
    },
    leftButton: {
        borderTopLeftRadius: 6,
        borderBottomLeftRadius: 6,
    },
    rightButton: {
        borderTopRightRadius: 6,
        borderBottomRightRadius: 6,
    },
    activeButton: {
        backgroundColor: 'white',
    },
    buttonText: {
        fontSize: 12,
        fontWeight: '600',
        color: 'rgba(255,255,255,0.8)',
    },
    activeButtonText: {
        color: '#8E2DE2',
        fontWeight: 'bold',
    },
    disabledText: {
        opacity: 0.4,
    },
});

export default LanguageToggle;
