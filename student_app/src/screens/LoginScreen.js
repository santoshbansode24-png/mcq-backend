import React, { useState } from 'react';
import { View, Text, TextInput, TouchableOpacity, StyleSheet, Alert, ActivityIndicator, StatusBar, Image, KeyboardAvoidingView, ScrollView, Platform } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { loginUser } from '../api/auth';
import config from '../api/config';

const LoginScreen = ({ navigation }) => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [isPasswordVisible, setIsPasswordVisible] = useState(false); // State for password visibility
    const [loading, setLoading] = useState(false);

    const handleLogin = async () => {
        // Trim inputs to avoid accidental spaces
        const trimmedEmail = email.trim();
        const trimmedPassword = password.trim();



        if (!trimmedEmail || !trimmedPassword) {
            Alert.alert('Error', 'Please enter email/mobile and password');
            return;
        }

        setLoading(true);
        try {
            const data = await loginUser(trimmedEmail, trimmedPassword);

            setLoading(false);

            if (data && data.status === 'success') {
                if (!data.data) {
                    throw new Error("Login successful but no user data received.");
                }

                // Save user session
                await AsyncStorage.setItem('user_data', JSON.stringify(data.data));

                Alert.alert('Success', `Welcome back, ${data.data.name || 'Student'}!`);
                if (navigation) {
                    if (!data.data.class_id || !data.data.board_type) {
                        navigation.replace('Setup', { user: data.data });
                    } else {
                        navigation.replace('AppSplash', { user: data.data });
                    }
                }
            } else {
                // Show the raw message from the server (e.g. "LOCAL SERVER: ...")
                const msg = data?.message || 'Invalid credentials or server error';
                console.warn("Login failed message:", msg);
                Alert.alert('Login Failed', msg);
            }
        } catch (error) {
            setLoading(false);
            console.error("Login Error Catch:", error);
            const errorMessage = error.message || 'Something went wrong';
            Alert.alert('Error', errorMessage);
        }
    };

    const diagnoseConnection = async () => {
        setLoading(true);
        let debugMsg = "Starting diagnostics...\n";

        try {
            // 1. Check Internet (Google)
            debugMsg += "1. Checking Internet (Google)... ";
            try {
                const googleRes = await fetch('https://www.google.com', { method: 'HEAD', mode: 'no-cors', timeout: 5000 });
                debugMsg += "OK ✅\n";
            } catch (e) {
                debugMsg += `FAIL ❌ (${e.message})\n`;
            }

            // 2. Check Backend Health
            debugMsg += `2. Checking Backend (${config.API_URL})... `;
            try {
                const backendRes = await fetch(`${config.API_URL}/health.php`, {
                    method: 'GET',
                    timeout: 25000,
                    headers: { 'Cache-Control': 'no-cache' }
                });
                debugMsg += `Status: ${backendRes.status} `;
                if (backendRes.ok) {
                    debugMsg += "OK ✅\n";
                    const text = await backendRes.text();
                    debugMsg += `Response: ${text.substring(0, 50)}...\n`;
                } else {
                    debugMsg += "FAIL ❌\n";
                }
            } catch (e) {
                debugMsg += `FAIL ❌ (${e.message})\n`;
                // Detailed Axios style error if available
                if (e.toJSON) {
                    debugMsg += `Details: ${JSON.stringify(e.toJSON())}\n`;
                }
            }

            Alert.alert("Diagnostic Results", debugMsg);
            console.log(debugMsg);

        } catch (err) {
            Alert.alert("Diagnostic Error", err.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <KeyboardAvoidingView
            style={styles.container}
            behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        >
            <StatusBar barStyle="dark-content" />
            <ScrollView contentContainerStyle={styles.scrollContent} keyboardShouldPersistTaps="handled">
                <View style={styles.content}>
                    <View style={styles.logoContainer}>
                        <Image
                            source={require('../../assets/veeru_login_logo.jpg')}
                            style={styles.logo}
                            resizeMode="contain"
                        />
                    </View>
                    <Text style={styles.subtitle}>Veeru - Learn Smarter</Text>

                    <View style={styles.form}>
                        <Text style={styles.label}>Email or Mobile Number</Text>
                        <TextInput
                            style={styles.input}
                            placeholder="Email or Mobile No."
                            value={email}
                            onChangeText={setEmail}
                            keyboardType="email-address"
                            autoCapitalize="none"
                        />

                        <Text style={styles.label}>Password</Text>
                        <View style={styles.passwordContainer}>
                            <TextInput
                                style={styles.passwordInput}
                                placeholder="Enter your password"
                                value={password}
                                onChangeText={setPassword}
                                secureTextEntry={!isPasswordVisible}
                            />
                            <TouchableOpacity onPress={() => setIsPasswordVisible(!isPasswordVisible)} style={styles.eyeIcon}>
                                <Ionicons name={isPasswordVisible ? 'eye-off' : 'eye'} size={24} color="#6b7280" />
                            </TouchableOpacity>
                        </View>

                        <TouchableOpacity
                            onPress={() => navigation.navigate('ForgotPassword')}
                            style={{ alignSelf: 'flex-end', marginTop: 8 }}
                        >
                            <Text style={{ color: '#4f46e5', fontWeight: 'bold' }}>Forgot Password?</Text>
                        </TouchableOpacity>

                        <TouchableOpacity
                            style={styles.button}
                            onPress={handleLogin}
                            disabled={loading}
                        >
                            {loading ? (
                                <ActivityIndicator color="#fff" />
                            ) : (
                                <Text style={styles.buttonText}>Login</Text>
                            )}
                        </TouchableOpacity>

                        <TouchableOpacity
                            onPress={() => navigation.navigate('Register')}
                            style={{ marginTop: 24, alignItems: 'center' }}
                        >
                            <Text style={{ color: '#666' }}>
                                Don't have an account? <Text style={{ color: '#4f46e5', fontWeight: 'bold' }}>Register Here</Text>
                            </Text>
                        </TouchableOpacity>
                    </View>
                </View>
            </ScrollView>
        </KeyboardAvoidingView>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: '#f5f7fa',
        paddingTop: 50,
    },
    content: {
        padding: 24,
        justifyContent: 'center',
    },
    scrollContent: {
        flexGrow: 1,
        justifyContent: 'center'
    },
    logoContainer: {
        alignItems: 'center',
        marginBottom: 20,
    },
    logo: {
        width: 150,
        height: 150,
    },
    title: {
        fontSize: 32,
        fontWeight: 'bold',
        color: '#333',
        marginBottom: 8,
        textAlign: 'center',
    },
    subtitle: {
        fontSize: 18,
        color: '#666',
        marginBottom: 48,
        textAlign: 'center',
    },
    form: {
        backgroundColor: '#fff',
        padding: 24,
        borderRadius: 16,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.1,
        shadowRadius: 8,
        elevation: 4,
    },
    label: {
        fontSize: 14,
        fontWeight: '600',
        color: '#333',
        marginBottom: 8,
        marginTop: 16,
    },
    input: {
        backgroundColor: '#f9fafb',
        borderWidth: 1,
        borderColor: '#e5e7eb',
        borderRadius: 12,
        padding: 16,
        fontSize: 16,
        color: '#333',
    },
    passwordContainer: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: '#f9fafb',
        borderWidth: 1,
        borderColor: '#e5e7eb',
        borderRadius: 12,
        paddingHorizontal: 16,
    },
    passwordInput: {
        flex: 1,
        paddingVertical: 16,
        fontSize: 16,
        color: '#333',
    },
    eyeIcon: {
        padding: 8,
    },
    button: {
        backgroundColor: '#4f46e5',
        padding: 16,
        borderRadius: 12,
        alignItems: 'center',
        marginTop: 32,
        marginBottom: 16,
    },
    buttonText: {
        color: '#fff',
        fontSize: 16,
        fontWeight: 'bold',
    },
    debugInfo: {
        marginTop: 20,
        padding: 10,
        backgroundColor: '#f0f0f0',
        borderRadius: 8,
        alignItems: 'center',
    },
    debugText: {
        fontSize: 12,
        color: '#666',
    }
});

export default LoginScreen;
