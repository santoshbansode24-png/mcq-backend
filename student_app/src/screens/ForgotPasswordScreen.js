import React, { useState } from 'react';
import { View, Text, TextInput, TouchableOpacity, StyleSheet, Alert, ActivityIndicator, KeyboardAvoidingView, Platform, ScrollView } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import axios from 'axios';
import { API_URL, BASE_URL } from '../api/config';
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
    const [email, setEmail] = useState('');
    const [mobile, setMobile] = useState('');
    const [pin, setPin] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [loading, setLoading] = useState(false);

    const handleResetPassword = async () => {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!email || !emailRegex.test(email.trim())) {
            Alert.alert('Invalid Email Address', 'Please enter a valid email address.');
            return;
        }

        if (!mobile || mobile.trim().length !== 10) {
            Alert.alert('Invalid Mobile Number', 'Please enter a 10-digit mobile number.');
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
            // Target the root or API forgot password endpoint
            const endpoint = `${API_URL}/forgot_password.php`;
            const response = await axios.post(endpoint, {
                email: email.trim(),
                mobile: mobile.trim(),
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
            const msg = error.response?.data?.message || 'Failed to reset password. If you forgot your PIN, please ask your Teacher or Admin.';
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
                        Enter your Registered Email, Mobile Number, and 4-Digit Security PIN to reset your password.
                    </Text>
                </View>

                {/* Form Section */}
                <View style={[styles.card, { backgroundColor: colors.cardBackground, borderColor: colors.border }]}>
                    <View style={styles.inputContainer}>
                        <Text style={[styles.label, { color: colors.textPrimary }]}>Registered Email ID</Text>
                        <View style={[styles.inputWrapper, { backgroundColor: colors.inputBg, borderColor: colors.border }]}>
                            <Ionicons name="mail-outline" size={20} color={colors.textSecondary} style={styles.inputIcon} />
                            <TextInput
                                style={[styles.input, { color: colors.textPrimary }]}
                                placeholder="student@example.com"
                                placeholderTextColor={colors.textSecondary}
                                value={email}
                                onChangeText={setEmail}
                                keyboardType="email-address"
                                autoCapitalize="none"
                            />
                        </View>
                    </View>

                    <View style={styles.inputContainer}>
                        <Text style={[styles.label, { color: colors.textPrimary }]}>Registered Mobile Number</Text>
                        <View style={[styles.inputWrapper, { backgroundColor: colors.inputBg, borderColor: colors.border }]}>
                            <Ionicons name="call-outline" size={20} color={colors.textSecondary} style={styles.inputIcon} />
                            <TextInput
                                style={[styles.input, { color: colors.textPrimary }]}
                                placeholder="10-digit mobile number"
                                placeholderTextColor={colors.textSecondary}
                                value={mobile}
                                onChangeText={setMobile}
                                keyboardType="phone-pad"
                                maxLength={10}
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
                                secureTextEntry={true}
                            />
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
                                secureTextEntry={true}
                            />
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
                                <Text style={styles.submitButtonText}>Reset Password Now</Text>
                            )}
                        </LinearGradient>
                    </TouchableOpacity>
                </View>

                {/* Back to Login Link */}
                <TouchableOpacity onPress={() => navigation.navigate('Login')} style={styles.backToLogin}>
                    <Text style={[styles.backToLoginText, { color: '#4F46E5' }]}>
                        Remember your password? <Text style={{ fontWeight: 'bold' }}>Login</Text>
                    </Text>
                </TouchableOpacity>
            </ScrollView>
        </KeyboardAvoidingView>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
    },
    scrollContent: {
        padding: 24,
        paddingTop: Platform.OS === 'ios' ? 60 : 40,
    },
    header: {
        alignItems: 'center',
        marginBottom: 24,
    },
    backButton: {
        position: 'absolute',
        left: 0,
        top: 0,
        padding: 8,
    },
    iconContainer: {
        width: 80,
        height: 80,
        borderRadius: 40,
        backgroundColor: '#EEF2FF',
        justifyContent: 'center',
        alignItems: 'center',
        marginBottom: 16,
        marginTop: 20,
    },
    title: {
        fontSize: 24,
        fontWeight: 'bold',
        marginBottom: 8,
    },
    subtitle: {
        fontSize: 14,
        textAlign: 'center',
        lineHeight: 20,
        paddingHorizontal: 12,
    },
    card: {
        padding: 20,
        borderRadius: 16,
        borderWidth: 1,
        elevation: 2,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.1,
        shadowRadius: 4,
    },
    inputContainer: {
        marginBottom: 16,
    },
    label: {
        fontSize: 14,
        fontWeight: '600',
        marginBottom: 6,
    },
    inputWrapper: {
        flexDirection: 'row',
        alignItems: 'center',
        borderWidth: 1,
        borderRadius: 12,
        paddingHorizontal: 12,
        height: 48,
    },
    inputIcon: {
        marginRight: 8,
    },
    input: {
        flex: 1,
        fontSize: 15,
    },
    submitButton: {
        marginTop: 8,
        borderRadius: 12,
        overflow: 'hidden',
    },
    gradientButton: {
        height: 50,
        justifyContent: 'center',
        alignItems: 'center',
    },
    submitButtonText: {
        color: '#FFFFFF',
        fontSize: 16,
        fontWeight: 'bold',
    },
    disabledButton: {
        opacity: 0.6,
    },
    backToLogin: {
        marginTop: 24,
        alignItems: 'center',
    },
    backToLoginText: {
        fontSize: 14,
    },
});

export default ForgotPasswordScreen;
