import React, { useState } from 'react';
import { View, Text, TextInput, TouchableOpacity, StyleSheet, Alert, ActivityIndicator, KeyboardAvoidingView, Platform, ScrollView } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import axios from 'axios';
import { API_URL } from '../api/config';
import { useTheme } from '../context/ThemeContext';

const ForgotPasswordScreen = ({ navigation }) => {
    const { theme } = useTheme();
    const [step, setStep] = useState(1); // 1: Send OTP, 2: Verify & Reset
    const [email, setEmail] = useState('');
    const [otp, setOtp] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [loading, setLoading] = useState(false);

    const handleSendOTP = async () => {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!email || !emailRegex.test(email.trim())) {
            Alert.alert('Invalid Email Address', 'Please enter a valid email address');
            return;
        }

        setLoading(true);
        try {
            const response = await axios.post(`${API_URL}/send_otp.php`, {
                email: email.trim()
            });

            if (response.data.status === 'success') {
                Alert.alert('OTP Sent', response.data.message);
                setStep(2);
            } else {
                Alert.alert('Error', response.data.message || 'Failed to send OTP');
            }
        } catch (error) {
            console.log("OTP Send Error:", error.response?.data || error.message);
            if (error.response) {
                Alert.alert('Error', error.response.data.message || 'Failed to send OTP');
            } else {
                Alert.alert('Error', 'Network error. Please check your connection.');
            }
        } finally {
            setLoading(false);
        }
    };

    const handleResetPassword = async () => {
        if (!otp || otp.length < 6) {
            Alert.alert('Invalid OTP', 'Please enter the 6-digit OTP');
            return;
        }
        if (!newPassword || newPassword.length < 6) {
            Alert.alert('Invalid Password', 'Password must be at least 6 characters');
            return;
        }
        if (newPassword !== confirmPassword) {
            Alert.alert('Mismatch', 'Passwords do not match');
            return;
        }

        setLoading(true);
        try {
            // Step 1: Verify OTP
            const verifyResponse = await axios.post(`${API_URL}/verify_otp.php`, {
                email: email.trim(),
                otp_code: otp
            });

            if (verifyResponse.data.status !== 'success') {
                Alert.alert('Error', verifyResponse.data.message || 'Invalid OTP');
                setLoading(false);
                return;
            }

            // Step 2: Reset Password
            // NOTE: sendResponse() wraps data inside a 'data' key: { status, message, data: { user_id, ... } }
            const resetResponse = await axios.post(`${API_URL}/reset_password.php`, {
                user_id: verifyResponse.data.data?.user_id,
                reset_token: verifyResponse.data.data?.reset_token,
                new_password: newPassword
            });

            if (resetResponse.data.status === 'success') {
                Alert.alert('Success', 'Password has been reset successfully!', [
                    { text: 'Login Now', onPress: () => navigation.navigate('Login') }
                ]);
            } else {
                Alert.alert('Error', resetResponse.data.message || 'Failed to reset password');
            }
        } catch (error) {
            console.log("OTP Verify Error:", error.response?.data || error.message);
            if (error.response) {
                Alert.alert('Error', error.response.data.message || 'Failed to reset password');
            } else {
                Alert.alert('Error', 'Network error. Please check your connection.');
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <KeyboardAvoidingView
            style={styles.container}
            behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
            keyboardVerticalOffset={Platform.OS === 'ios' ? 0 : 40}
        >
            <LinearGradient
                colors={['#4f46e5', '#3b82f6', '#f8fafc']}
                locations={[0, 0.4, 1]}
                style={styles.backgroundGradient}
            />

            <ScrollView contentContainerStyle={styles.scrollContent} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
                <TouchableOpacity style={styles.backButton} onPress={() => navigation.goBack()}>
                    <Ionicons name="arrow-back" size={24} color="white" />
                </TouchableOpacity>

                <View style={styles.headerContainer}>
                    <Text style={styles.title}>FORGOT PASSWORD</Text>
                    <Text style={styles.subtitle}>
                        {step === 1 ? 'Enter your registered email address' : 'Enter OTP and new password'}
                    </Text>
                </View>

                <View style={styles.formContainer}>
                    {step === 1 ? (
                        <>
                            <View style={styles.inputWrapper}>
                                <Text style={styles.label}>EMAIL ADDRESS</Text>
                                <View style={styles.inputContainer}>
                                    <Ionicons name="mail-outline" size={20} color="#94a3b8" style={styles.inputIcon} />
                                    <TextInput
                                        style={styles.input}
                                        placeholder="Enter your registered email"
                                        placeholderTextColor="#94a3b8"
                                        keyboardType="email-address"
                                        autoCapitalize="none"
                                        value={email}
                                        onChangeText={setEmail}
                                    />
                                </View>
                            </View>

                            <TouchableOpacity
                                style={styles.buttonShadow}
                                activeOpacity={0.8}
                                onPress={handleSendOTP}
                                disabled={loading}
                            >
                                <LinearGradient
                                    colors={['#4f46e5', '#6366f1']}
                                    start={{ x: 0, y: 0 }}
                                    end={{ x: 1, y: 0 }}
                                    style={styles.actionButton}
                                >
                                    {loading ? <ActivityIndicator color="#fff" /> : <Text style={styles.actionButtonText}>SEND OTP</Text>}
                                </LinearGradient>
                            </TouchableOpacity>
                        </>
                    ) : (
                        <>
                            <View style={styles.inputWrapper}>
                                <Text style={styles.label}>ENTER OTP</Text>
                                <View style={styles.inputContainer}>
                                    <Ionicons name="key-outline" size={20} color="#94a3b8" style={styles.inputIcon} />
                                    <TextInput
                                        style={styles.input}
                                        placeholder="6-digit OTP"
                                        placeholderTextColor="#94a3b8"
                                        keyboardType="number-pad"
                                        maxLength={6}
                                        value={otp}
                                        onChangeText={setOtp}
                                    />
                                </View>
                            </View>

                            <View style={styles.inputWrapper}>
                                <Text style={styles.label}>NEW PASSWORD</Text>
                                <View style={styles.inputContainer}>
                                    <Ionicons name="lock-closed-outline" size={20} color="#94a3b8" style={styles.inputIcon} />
                                    <TextInput
                                        style={styles.input}
                                        placeholder="New Password"
                                        placeholderTextColor="#94a3b8"
                                        secureTextEntry
                                        value={newPassword}
                                        onChangeText={setNewPassword}
                                    />
                                </View>
                            </View>

                            <View style={styles.inputWrapper}>
                                <Text style={styles.label}>CONFIRM NEW PASSWORD</Text>
                                <View style={styles.inputContainer}>
                                    <Ionicons name="shield-checkmark-outline" size={20} color="#94a3b8" style={styles.inputIcon} />
                                    <TextInput
                                        style={styles.input}
                                        placeholder="Confirm Password"
                                        placeholderTextColor="#94a3b8"
                                        secureTextEntry
                                        value={confirmPassword}
                                        onChangeText={setConfirmPassword}
                                    />
                                </View>
                            </View>

                            <TouchableOpacity
                                style={styles.buttonShadow}
                                activeOpacity={0.8}
                                onPress={handleResetPassword}
                                disabled={loading}
                            >
                                <LinearGradient
                                    colors={['#4f46e5', '#6366f1']}
                                    start={{ x: 0, y: 0 }}
                                    end={{ x: 1, y: 0 }}
                                    style={styles.actionButton}
                                >
                                    {loading ? <ActivityIndicator color="#fff" /> : <Text style={styles.actionButtonText}>RESET PASSWORD</Text>}
                                </LinearGradient>
                            </TouchableOpacity>
                        </>
                    )}
                </View>
            </ScrollView>
        </KeyboardAvoidingView>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: '#f8fafc',
    },
    backgroundGradient: {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        height: Platform.OS === 'ios' ? 400 : 350,
    },
    scrollContent: {
        flexGrow: 1,
        justifyContent: 'center',
        paddingHorizontal: 24,
        paddingTop: Platform.OS === 'ios' ? 60 : 80,
        paddingBottom: 40,
    },
    backButton: {
        position: 'absolute',
        top: Platform.OS === 'ios' ? 60 : 40,
        left: 20,
        zIndex: 10,
        width: 40,
        height: 40,
        justifyContent: 'center',
        alignItems: 'center',
        backgroundColor: 'rgba(255,255,255,0.2)',
        borderRadius: 20,
    },
    headerContainer: {
        alignItems: 'center',
        marginBottom: 30,
        marginTop: 20,
    },
    title: {
        fontSize: 28,
        color: '#fff',
        fontFamily: 'NotoSans-Bold',
        marginBottom: 5,
        textShadowColor: 'rgba(0, 0, 0, 0.1)',
        textShadowOffset: { width: 0, height: 1 },
        textShadowRadius: 4,
        textAlign: 'center',
    },
    subtitle: {
        fontSize: 16,
        color: 'rgba(255,255,255,0.9)',
        fontFamily: 'NotoSans-Regular',
        textAlign: 'center',
    },
    formContainer: {
        backgroundColor: '#ffffff',
        borderRadius: 24,
        padding: 24,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 15 },
        shadowOpacity: 0.1,
        shadowRadius: 30,
        elevation: 8,
    },
    inputWrapper: {
        marginBottom: 20,
    },
    label: {
        fontSize: 12,
        color: '#64748b',
        fontFamily: 'NotoSans-Bold',
        marginBottom: 8,
        marginLeft: 4,
        letterSpacing: 0.5,
    },
    inputContainer: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: '#f1f5f9',
        borderRadius: 16,
        borderWidth: 1,
        borderColor: '#e2e8f0',
        paddingHorizontal: 16,
        height: 56,
    },
    inputIcon: {
        marginRight: 10,
    },
    input: {
        flex: 1,
        fontSize: 16,
        color: '#0f172a',
        fontFamily: 'NotoSans-Regular',
        height: '100%',
    },
    buttonShadow: {
        shadowColor: '#4f46e5',
        shadowOffset: { width: 0, height: 8 },
        shadowOpacity: 0.3,
        shadowRadius: 16,
        elevation: 8,
        marginTop: 10,
    },
    actionButton: {
        height: 56,
        borderRadius: 16,
        justifyContent: 'center',
        alignItems: 'center',
    },
    actionButtonText: {
        color: '#ffffff',
        fontSize: 16,
        fontFamily: 'NotoSans-Bold',
        letterSpacing: 1,
    },
});

export default ForgotPasswordScreen;
