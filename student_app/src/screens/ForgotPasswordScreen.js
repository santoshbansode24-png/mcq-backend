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
    const [mobile, setMobile] = useState('');
    const [otp, setOtp] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [loading, setLoading] = useState(false);

    const handleSendOTP = async () => {
        if (!mobile || mobile.length < 10) {
            Alert.alert('Invalid Mobile', 'Please enter a valid mobile number');
            return;
        }

        setLoading(true);
        try {
            const response = await axios.post(`${API_URL}/otp.php`, {
                action: 'send_otp',
                mobile: mobile
            });

            if (response.data.status === 'success') {
                Alert.alert('OTP Sent', 'Please check your SMS for the OTP.');
                if (response.data.debug_otp) {
                    console.log('DEBUG OTP:', response.data.debug_otp); // For testing
                }
                setStep(2);
            } else {
                Alert.alert('Error', response.data.message || 'Failed to send OTP');
            }
        } catch (error) {
            console.error(error);
            Alert.alert('Error', 'Network error or server issue');
        } finally {
            setLoading(false);
        }
    };

    const handleResetPassword = async () => {
        if (!otp || otp.length < 4) {
            Alert.alert('Invalid OTP', 'Please enter the 4-digit OTP');
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
            const response = await axios.post(`${API_URL}/otp.php`, {
                action: 'reset_password',
                mobile: mobile,
                otp: otp,
                new_password: newPassword
            });

            if (response.data.status === 'success') {
                Alert.alert('Success', 'Password has been reset successfully!', [
                    { text: 'Login Now', onPress: () => navigation.navigate('Login') }
                ]);
            } else {
                Alert.alert('Error', response.data.message || 'Failed to reset password');
            }
        } catch (error) {
            console.error(error);
            Alert.alert('Error', 'Network error or server issue');
        } finally {
            setLoading(false);
        }
    };

    return (
        <KeyboardAvoidingView
            behavior={Platform.OS === "ios" ? "padding" : "height"}
            style={styles.container}
        >
            <LinearGradient colors={['#4c669f', '#3b5998', '#192f6a']} style={styles.background}>
                <ScrollView contentContainerStyle={styles.scrollContent}>
                    <TouchableOpacity style={styles.backButton} onPress={() => navigation.goBack()}>
                        <Ionicons name="arrow-back" size={24} color="white" />
                    </TouchableOpacity>

                    <Text style={styles.title}>Forgot Password</Text>
                    <Text style={styles.subtitle}>
                        {step === 1 ? 'Enter your registered mobile number' : 'Enter OTP and new password'}
                    </Text>

                    <View style={styles.card}>
                        {step === 1 ? (
                            <>
                                <View style={styles.inputContainer}>
                                    <Ionicons name="call-outline" size={20} color="#666" style={styles.icon} />
                                    <TextInput
                                        style={styles.input}
                                        placeholder="Mobile Number"
                                        placeholderTextColor="#999"
                                        keyboardType="phone-pad"
                                        maxLength={10}
                                        value={mobile}
                                        onChangeText={setMobile}
                                    />
                                </View>

                                <TouchableOpacity
                                    style={styles.button}
                                    onPress={handleSendOTP}
                                    disabled={loading}
                                >
                                    {loading ? <ActivityIndicator color="white" /> : <Text style={styles.buttonText}>Send OTP</Text>}
                                </TouchableOpacity>
                            </>
                        ) : (
                            <>
                                <View style={styles.inputContainer}>
                                    <Ionicons name="key-outline" size={20} color="#666" style={styles.icon} />
                                    <TextInput
                                        style={styles.input}
                                        placeholder="Enter OTP"
                                        placeholderTextColor="#999"
                                        keyboardType="number-pad"
                                        maxLength={6}
                                        value={otp}
                                        onChangeText={setOtp}
                                    />
                                </View>

                                <View style={styles.inputContainer}>
                                    <Ionicons name="lock-closed-outline" size={20} color="#666" style={styles.icon} />
                                    <TextInput
                                        style={styles.input}
                                        placeholder="New Password"
                                        placeholderTextColor="#999"
                                        secureTextEntry
                                        value={newPassword}
                                        onChangeText={setNewPassword}
                                    />
                                </View>

                                <View style={styles.inputContainer}>
                                    <Ionicons name="lock-closed-outline" size={20} color="#666" style={styles.icon} />
                                    <TextInput
                                        style={styles.input}
                                        placeholder="Confirm New Password"
                                        placeholderTextColor="#999"
                                        secureTextEntry
                                        value={confirmPassword}
                                        onChangeText={setConfirmPassword}
                                    />
                                </View>

                                <TouchableOpacity
                                    style={styles.button}
                                    onPress={handleResetPassword}
                                    disabled={loading}
                                >
                                    {loading ? <ActivityIndicator color="white" /> : <Text style={styles.buttonText}>Reset Password</Text>}
                                </TouchableOpacity>
                            </>
                        )}
                    </View>
                </ScrollView>
            </LinearGradient>
        </KeyboardAvoidingView>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1 },
    background: { flex: 1 },
    scrollContent: { flexGrow: 1, justifyContent: 'center', padding: 20 },
    backButton: { position: 'absolute', top: 40, left: 20, zIndex: 10 },
    title: { fontSize: 32, fontWeight: 'bold', color: 'white', textAlign: 'center', marginBottom: 10, marginTop: 60 },
    subtitle: { fontSize: 16, color: 'rgba(255,255,255,0.8)', textAlign: 'center', marginBottom: 40 },
    card: { backgroundColor: 'white', borderRadius: 20, padding: 30, elevation: 10, shadowColor: '#000', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.3, shadowRadius: 5 },
    inputContainer: { flexDirection: 'row', alignItems: 'center', borderBottomWidth: 1, borderBottomColor: '#ddd', marginBottom: 20, paddingBottom: 5 },
    icon: { marginRight: 10 },
    input: { flex: 1, fontSize: 16, color: '#333', paddingVertical: 10 },
    button: { backgroundColor: '#4c669f', padding: 15, borderRadius: 10, alignItems: 'center', marginTop: 10 },
    buttonText: { color: 'white', fontSize: 18, fontWeight: 'bold' },
});

export default ForgotPasswordScreen;
