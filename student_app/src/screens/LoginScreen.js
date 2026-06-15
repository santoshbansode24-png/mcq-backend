import React, { useState } from 'react';
import { View, Text, TextInput, TouchableOpacity, StyleSheet, Alert, ActivityIndicator, StatusBar, Image, KeyboardAvoidingView, ScrollView, Platform, Dimensions, Linking } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { LinearGradient } from 'expo-linear-gradient';
import { loginUser } from '../api/auth';
import { googleLogin } from '../api/googleAuth';
import config, { BASE_URL } from '../api/config';
import * as Google from 'expo-auth-session/providers/google';
import * as WebBrowser from 'expo-web-browser';
import * as AuthSession from 'expo-auth-session';

WebBrowser.maybeCompleteAuthSession();

const { width, height } = Dimensions.get('window');

const LoginScreen = ({ navigation }) => {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [isPasswordVisible, setIsPasswordVisible] = useState(false);
    const [loading, setLoading] = useState(false);
    const [googleLoading, setGoogleLoading] = useState(false);

    const [errorMsg, setErrorMsg] = useState('');

    const [request, response, promptAsync] = Google.useAuthRequest({
        androidClientId: '1047709706514-4rvops6i9hb0id374ndb0rgf0bajrta5.apps.googleusercontent.com',
        webClientId: '1047709706514-o46ho477qi3em7o1jncubheu59qe1tk2.apps.googleusercontent.com',
        redirectUri: AuthSession.makeRedirectUri({
            scheme: 'com.veeru.app',
        }),
    });

    React.useEffect(() => {
        if (response?.type === 'success') {
            const { authentication } = response;
            getUserInfo(authentication.accessToken);
        } else if (response?.type === 'error' || response?.type === 'cancel') {
            setGoogleLoading(false);
        }
    }, [response]);

    const getUserInfo = async (token) => {
        if (!token) return;
        try {
            const res = await fetch('https://www.googleapis.com/userinfo/v2/me', {
                headers: { Authorization: `Bearer ${token}` },
            });
            const user = await res.json();

            const userDataForBackend = {
                email: user.email,
                name: user.name,
                id: user.id,
                photo: user.picture
            };

            const data = await googleLogin(userDataForBackend);
            setGoogleLoading(false);

            if (data && data.status === 'success') {
                const userData = data.data;
                await AsyncStorage.setItem('user_data', JSON.stringify(userData));

                // Check if user needs setup (new user or missing critical info)
                if (userData.is_new_user || !userData.class_id || !userData.board_type) {
                    navigation.replace('Setup', { user: userData });
                } else {
                    navigation.replace('Main', { user: userData });
                }
            } else if (data && data.status === 'new_user') {
                // Not a user yet, redirect to full registration screen with pre-filled data
                navigation.navigate('Register', { googleData: data.data });
            } else {
                Alert.alert('Login Failed', data.message || 'Error connecting to database');
            }
        } catch (error) {
            console.error('Error fetching user info:', error);
            setGoogleLoading(false);
            Alert.alert('Error', 'Failed to get your Google profile info');
        }
    };

    const handleGoogleSignIn = () => {
        setGoogleLoading(true);
        // Force account selection every time
        promptAsync({
            showInRecents: true,
        });
    };

    const handleLogin = async () => {
        const trimmedEmail = email.trim();
        const trimmedPassword = password.trim();
        setErrorMsg(''); // Reset error message

        if (!trimmedEmail || !trimmedPassword) {
            setErrorMsg('Please enter email/mobile and password');
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

                await AsyncStorage.setItem('user_data', JSON.stringify(data.data));

                if (navigation) {
                    if (!data.data.class_id || !data.data.board_type) {
                        navigation.replace('Setup', { user: data.data });
                    } else {
                        navigation.replace('Main', { user: data.data });
                    }
                }
            } else {
                const msg = data?.message || 'Invalid credentials or server error';
                console.warn("Login failed message:", msg);
                setErrorMsg(msg);
            }
        } catch (error) {
            setLoading(false);
            const errStr = error.response
                ? `Status: ${error.response.status} - ${JSON.stringify(error.response.data)}`
                : error.message || error.toString();
            console.error("Login Error:", errStr);
            setErrorMsg(errStr);
        }
    };

    return (
        <KeyboardAvoidingView
            style={styles.container}
            behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
            keyboardVerticalOffset={Platform.OS === 'ios' ? 0 : 40}
        >
            <StatusBar barStyle="light-content" translucent backgroundColor="transparent" />

            {/* Background Gradient */}
            <LinearGradient
                colors={['#4f46e5', '#3b82f6', '#f8fafc']}
                locations={[0, 0.4, 1]}
                style={styles.backgroundGradient}
            />

            <ScrollView
                contentContainerStyle={styles.scrollContent}
                keyboardShouldPersistTaps="handled"
                showsVerticalScrollIndicator={false}
            >
                <View style={styles.headerContainer}>
                    <View style={styles.logoContainer}>
                        <Image
                            source={require('../../assets/veeru_login_logo.jpg')}
                            style={styles.logo}
                            resizeMode="contain"
                        />
                    </View>
                    <Text style={styles.welcomeText}>Welcome Back!</Text>
                    <Text style={styles.subtitle}>Veeru - Learn Smarter</Text>
                </View>

                <View style={styles.formContainer}>
                    <View style={styles.inputWrapper}>
                        <Text style={styles.label}>EMAIL OR MOBILE NUMBER</Text>
                        <View style={styles.inputContainer}>
                            <Ionicons name="mail-outline" size={20} color="#94a3b8" style={styles.inputIcon} />
                            <TextInput
                                style={styles.input}
                                placeholder="Enter email or mobile no."
                                placeholderTextColor="#94a3b8"
                                value={email}
                                onChangeText={setEmail}
                                keyboardType="email-address"
                                autoCapitalize="none"
                            />
                        </View>
                    </View>

                    <View style={styles.inputWrapper}>
                        <Text style={styles.label}>PASSWORD</Text>
                        <View style={styles.inputContainer}>
                            <Ionicons name="lock-closed-outline" size={20} color="#94a3b8" style={styles.inputIcon} />
                            <TextInput
                                style={styles.input}
                                placeholder="Enter your password"
                                placeholderTextColor="#94a3b8"
                                value={password}
                                onChangeText={setPassword}
                                secureTextEntry={!isPasswordVisible}
                                autoCapitalize="none"
                                autoCorrect={false}
                            />
                            <TouchableOpacity onPress={() => setIsPasswordVisible(!isPasswordVisible)} style={styles.eyeIcon}>
                                <Ionicons name={isPasswordVisible ? 'eye-off' : 'eye'} size={20} color="#94a3b8" />
                            </TouchableOpacity>
                        </View>
                    </View>

                    <TouchableOpacity
                        onPress={() => navigation.navigate('ForgotPassword')}
                        style={styles.forgotPassword}
                    >
                        <Text style={styles.forgotPasswordText}>Forgot Password?</Text>
                    </TouchableOpacity>

                    <TouchableOpacity
                        style={styles.buttonShadow}
                        activeOpacity={0.8}
                        onPress={handleLogin}
                        disabled={loading}
                    >
                        <LinearGradient
                            colors={['#4f46e5', '#6366f1']}
                            start={{ x: 0, y: 0 }}
                            end={{ x: 1, y: 0 }}
                            style={styles.loginButton}
                        >
                            {loading ? (
                                <ActivityIndicator color="#fff" />
                            ) : (
                                <Text style={styles.loginButtonText}>LOGIN</Text>
                            )}
                        </LinearGradient>
                    </TouchableOpacity>

                    {/* OR Separator */}
                    <View style={styles.separatorContainer}>
                        <View style={styles.separatorLine} />
                        <Text style={styles.separatorText}>OR CONTINUE WITH</Text>
                        <View style={styles.separatorLine} />
                    </View>

                    {/* Google Login Button */}
                    <TouchableOpacity
                        style={styles.googleButton}
                        onPress={handleGoogleSignIn}
                        disabled={googleLoading}
                    >
                        {googleLoading ? (
                            <ActivityIndicator color="#4f46e5" />
                        ) : (
                            <View style={styles.googleButtonContent}>
                                <Ionicons name="logo-google" size={20} color="#EA4335" />
                                <Text style={styles.googleButtonText}>Sign in with Google</Text>
                            </View>
                        )}
                    </TouchableOpacity>

                    {errorMsg ? (
                        <Text style={styles.errorText}>
                            {errorMsg}
                        </Text>
                    ) : null}

                    <View style={styles.registerContainer}>
                        <Text style={styles.registerText}>Don't have an account? </Text>
                        <TouchableOpacity onPress={() => navigation.navigate('Register')}>
                            <Text style={styles.registerLink}>REGISTER HERE</Text>
                        </TouchableOpacity>
                    </View>
                </View>

                <TouchableOpacity
                    onPress={() => Linking.openURL(`${config.ROOT_URL}/privacy.php`)}
                    style={styles.privacyContainer}
                >
                    <Text style={styles.privacyText}>
                        By logging in, you agree to our <Text style={styles.privacyLink}>Privacy Policy</Text>
                    </Text>
                </TouchableOpacity>
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
        height: height * 0.5,
    },
    scrollContent: {
        flexGrow: 1,
        justifyContent: 'center',
        paddingHorizontal: 24,
        paddingTop: Platform.OS === 'ios' ? 60 : 80,
        paddingBottom: Platform.OS === 'ios' ? 40 : 150,
    },
    headerContainer: {
        alignItems: 'center',
        marginBottom: 30,
    },
    logoContainer: {
        backgroundColor: '#fff',
        padding: 15,
        borderRadius: 25,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 10 },
        shadowOpacity: 0.15,
        shadowRadius: 20,
        elevation: 10,
        marginBottom: 20,
    },
    logo: {
        width: 100,
        height: 100,
        borderRadius: 20,
    },
    welcomeText: {
        fontSize: 28,
        color: '#fff',
        fontFamily: 'NotoSans-Bold',
        marginBottom: 5,
        textShadowColor: 'rgba(0, 0, 0, 0.1)',
        textShadowOffset: { width: 0, height: 1 },
        textShadowRadius: 4,
    },
    subtitle: {
        fontSize: 16,
        color: 'rgba(255,255,255,0.9)',
        fontFamily: 'NotoSans-Regular',
        marginBottom: 10,
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
    eyeIcon: {
        padding: 10,
    },
    forgotPassword: {
        alignSelf: 'flex-end',
        marginBottom: 24,
    },
    forgotPasswordText: {
        color: '#4f46e5',
        fontSize: 14,
        fontFamily: 'NotoSans-Bold',
    },
    buttonShadow: {
        shadowColor: '#4f46e5',
        shadowOffset: { width: 0, height: 8 },
        shadowOpacity: 0.3,
        shadowRadius: 16,
        elevation: 8,
        marginBottom: 24,
    },
    loginButton: {
        height: 56,
        borderRadius: 16,
        justifyContent: 'center',
        alignItems: 'center',
    },
    loginButtonText: {
        color: '#ffffff',
        fontSize: 16,
        fontFamily: 'NotoSans-Bold',
        letterSpacing: 1,
    },
    errorText: {
        color: '#ef4444',
        textAlign: 'center',
        marginBottom: 16,
        fontFamily: 'NotoSans-Regular',
        fontSize: 12,
    },
    registerContainer: {
        flexDirection: 'row',
        justifyContent: 'center',
        alignItems: 'center',
        marginTop: 8,
    },
    registerText: {
        color: '#64748b',
        fontSize: 14,
        fontFamily: 'NotoSans-Regular',
    },
    registerLink: {
        color: '#4f46e5',
        fontSize: 14,
        fontFamily: 'NotoSans-Bold',
    },
    privacyContainer: {
        marginTop: 30,
        alignItems: 'center',
    },
    privacyText: {
        color: '#94a3b8',
        fontSize: 12,
        fontFamily: 'NotoSans-Regular',
    },
    privacyLink: {
        color: '#6366f1',
        textDecorationLine: 'underline',
    },
    separatorContainer: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 24,
    },
    separatorLine: {
        flex: 1,
        height: 1,
        backgroundColor: '#e2e8f0',
    },
    separatorText: {
        paddingHorizontal: 12,
        color: '#94a3b8',
        fontSize: 10,
        fontFamily: 'NotoSans-Bold',
    },
    googleButton: {
        height: 56,
        borderRadius: 16,
        backgroundColor: '#ffffff',
        borderWidth: 1,
        borderColor: '#e2e8f0',
        justifyContent: 'center',
        alignItems: 'center',
        marginBottom: 24,
    },
    googleButtonContent: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
    },
    googleButtonText: {
        marginLeft: 10,
        color: '#0f172a',
        fontSize: 16,
        fontFamily: 'NotoSans-Bold',
    },
});

export default LoginScreen;
