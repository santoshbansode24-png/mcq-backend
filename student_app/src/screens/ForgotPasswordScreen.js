import React, { useState } from 'react';
import { View, Text, TextInput, TouchableOpacity, StyleSheet, Alert, ActivityIndicator, KeyboardAvoidingView, Platform, ScrollView } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import axios from 'axios';
import { API_URL } from '../api/config';
import { useTheme } from '../context/ThemeContext';

const ForgotPasswordScreen = ({ navigation }) => {
    const { theme } = useTheme();
    const colors = {
        background: theme?.background || theme?.colors?.background || '#F8FAFC',
        textPrimary: theme?.text || theme?.colors?.textPrimary || '#0F172A',
        textSecondary: theme?.textSecondary || theme?.colors?.textSecondary || '#475569',
        cardBackground: theme?.card || theme?.colors?.cardBackground || '#FFFFFF',
        inputBg: theme?.background || theme?.colors?.inputBg || '#F8FAFC',
        border: theme?.border || theme?.colors?.border || '#E2E8F0',
    };
    const [identifier, setIdentifier] = useState('');
    const [pin, setPin] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [showNewPassword, setShowNewPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const [loading, setLoading] = useState(false);

    const handleResetPassword = async () => {
        const trimmedIdentifier = identifier.trim();
        if (!trimmedIdentifier) {
            Alert.alert('Required Field', 'Please enter your registered Mobile Number or Email ID.');
            return;
        }

        if (!pin || pin.trim().length !== 4) {
            Alert.alert('Invalid PIN', 'Security PIN must be exactly 4 digits.');
            return;
        }

        if (!newPassword || newPassword.length < 6) {
            Alert.alert('Invalid Password', 'Password must be at least 6 characters.');
            return;
        }

        if (newPassword !== confirmPassword) {
            Alert.alert('Mismatch', 'New password and confirm password do not match.');
            return;
        }

        setLoading(true);
        try {
            const endpoint = `${API_URL}/forgot_password.php`;
            const response = await axios.post(endpoint, {
                identifier: trimmedIdentifier,
                security_pin: pin.trim(),
                new_password: newPassword
            });

            if (response.data && response.data.status === 'success') {
                Alert.alert('Password Reset Success 🎉', 'Your password has been reset successfully! You can now log in with your new password.', [
                    { text: 'Login Now', onPress: () => navigation.navigate('Login') }
                ]);
            } else {
                Alert.alert('Reset Failed', response.data?.message || 'Failed to reset password. Please check your details.');
            }
        } catch (error) {
            console.log("Forgot Password Error:", error.response?.data || error.message);
            const msg = error.response?.data?.message || 'Failed to reset password. Please check your details.';
            Alert.alert('Reset Error', msg);
        } finally {
            setLoading(false);
        }
    };

    return (
        <KeyboardAvoidingView 
            behavior={Platform.OS === 'ios' ? 'padding' : 'height'} 
            style={[styles.container, { backgroundColor: colors.background }]}
        >
            <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}>
                {/* Header Section */}
                <View style={styles.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                        <Ionicons name="arrow-back" size={24} color={colors.textPrimary} />
                    </TouchableOpacity>
                    <View style={styles.iconContainer}>
                        <Ionicons name="key-outline" size={40} color="#4F46E5" />
                    </View>
                    <Text style={[styles.title, { color: colors.textPrimary }]}>Forgot Password?</Text>
                    <Text style={[styles.subtitle, { color: colors.textSecondary }]}>
                        Enter your Registered Mobile Number or Email ID and 4-Digit Security PIN to reset your password.
                    </Text>
                </View>

                {/* Form Section */}
                <View style={[styles.card, { backgroundColor: colors.cardBackground, borderColor: colors.border }]}>
                    <View style={styles.inputContainer}>
                        <Text style={[styles.label, { color: colors.textPrimary }]}>Mobile Number or Email ID</Text>
                        <View style={[styles.inputWrapper, { backgroundColor: colors.inputBg, borderColor: colors.border }]}>
                            <Ionicons name="person-outline" size={20} color={colors.textSecondary} style={styles.inputIcon} />
                            <TextInput
                                style={[styles.input, { color: colors.textPrimary }]}
                                placeholder="Enter mobile no or email id"
                                placeholderTextColor={colors.textSecondary}
                                value={identifier}
                                onChangeText={setIdentifier}
                                autoCapitalize="none"
                                autoCorrect={false}
                            />
                        </View>
                    </View>

                    <View style={styles.inputContainer}>
                        <Text style={[styles.label, { color: colors.textPrimary }]}>4-Digit Security PIN</Text>
                        <View style={[styles.inputWrapper, { backgroundColor: colors.inputBg, borderColor: colors.border }]}>
                            <Ionicons name="shield-checkmark-outline" size={20} color={colors.textSecondary} style={styles.inputIcon} />
                            <TextInput
                                style={[styles.input, { color: colors.textPrimary }]}
                                placeholder="e.g. 1234"
                                placeholderTextColor={colors.textSecondary}
                                value={pin}
                                onChangeText={setPin}
                                keyboardType="number-pad"
                                maxLength={4}
                                secureTextEntry={true}
                            />
                        </View>
                    </View>

                    <View style={styles.inputContainer}>
                        <Text style={[styles.label, { color: colors.textPrimary }]}>New Password</Text>
                        <View style={[styles.inputWrapper, { backgroundColor: colors.inputBg, borderColor: colors.border }]}>
                            <Ionicons name="lock-closed-outline" size={20} color={colors.textSecondary} style={styles.inputIcon} />
                            <TextInput
                                style={[styles.input, { color: colors.textPrimary }]}
                                placeholder="Minimum 6 characters"
                                placeholderTextColor={colors.textSecondary}
                                value={newPassword}
                                onChangeText={setNewPassword}
                                secureTextEntry={!showNewPassword}
                            />
                            <TouchableOpacity onPress={() => setShowNewPassword(!showNewPassword)} style={styles.eyeIconContainer}>
                                <Ionicons name={showNewPassword ? "eye" : "eye-off"} size={20} color={colors.textSecondary} />
                            </TouchableOpacity>
                        </View>
                    </View>

                    <View style={styles.inputContainer}>
                        <Text style={[styles.label, { color: colors.textPrimary }]}>Confirm New Password</Text>
                        <View style={[styles.inputWrapper, { backgroundColor: colors.inputBg, borderColor: colors.border }]}>
                            <Ionicons name="lock-closed-outline" size={20} color={colors.textSecondary} style={styles.inputIcon} />
                            <TextInput
                                style={[styles.input, { color: colors.textPrimary }]}
                                placeholder="Re-enter new password"
                                placeholderTextColor={colors.textSecondary}
                                value={confirmPassword}
                                onChangeText={setConfirmPassword}
                                secureTextEntry={!showConfirmPassword}
                            />
                            <TouchableOpacity onPress={() => setShowConfirmPassword(!showConfirmPassword)} style={styles.eyeIconContainer}>
                                <Ionicons name={showConfirmPassword ? "eye" : "eye-off"} size={20} color={colors.textSecondary} />
                            </TouchableOpacity>
                        </View>
                    </View>

                    <TouchableOpacity 
                        style={[styles.submitButton, loading && styles.disabledButton]} 
                        onPress={handleResetPassword}
                        disabled={loading}
                    >
                        <LinearGradient
                            colors={['#4F46E5', '#3730A3']}
                            style={styles.gradientButton}
                        >
                            {loading ? (
                                <ActivityIndicator color="#FFFFFF" size="small" />
                            ) : (
                                <Text style={styles.submitButtonText}>Reset Password</Text>
                            )}
                        </LinearGradient>
                    </TouchableOpacity>
                </View>
            </ScrollView>
        </KeyboardAvoidingView>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
    },
    scrollContent: {
        flexGrow: 1,
        paddingHorizontal: 24,
        paddingTop: 60,
        paddingBottom: 40,
    },
    header: {
        marginBottom: 32,
        alignItems: 'flex-start',
    },
    backButton: {
        marginBottom: 20,
        padding: 4,
    },
    iconContainer: {
        width: 64,
        height: 64,
        borderRadius: 20,
        backgroundColor: 'rgba(79, 70, 229, 0.1)',
        justify: 'center',
        alignItems: 'center',
        marginBottom: 16,
    },
    title: {
        fontSize: 28,
        fontWeight: 'bold',
        marginBottom: 8,
    },
    subtitle: {
        fontSize: 15,
        lineHeight: 22,
    },
    card: {
        borderRadius: 24,
        padding: 24,
        borderWidth: 1,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.05,
        shadowRadius: 12,
        elevation: 2,
    },
    inputContainer: {
        marginBottom: 20,
    },
    label: {
        fontSize: 14,
        fontWeight: '600',
        marginBottom: 8,
    },
    inputWrapper: {
        flexDirection: 'row',
        alignItems: 'center',
        borderRadius: 12,
        borderWidth: 1,
        paddingHorizontal: 16,
        height: 52,
    },
    inputIcon: {
        marginRight: 12,
    },
    input: {
        flex: 1,
        fontSize: 16,
        height: '100%',
    },
    submitButton: {
        marginTop: 12,
        borderRadius: 14,
        overflow: 'hidden',
    },
    disabledButton: {
        opacity: 0.7,
    },
    gradientButton: {
        height: 54,
        justifyContent: 'center',
        alignItems: 'center',
    },
    submitButtonText: {
        color: '#FFFFFF',
        fontSize: 16,
        fontWeight: 'bold',
    },
    eyeIconContainer: {
        padding: 6,
        marginLeft: 8,
    },
});

export default ForgotPasswordScreen;
