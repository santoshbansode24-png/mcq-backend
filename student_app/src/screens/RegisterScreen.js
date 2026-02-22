import React, { useState, useEffect } from 'react';
import { View, Text, TextInput, TouchableOpacity, StyleSheet, Alert, ActivityIndicator, StatusBar, ScrollView, Modal, FlatList, KeyboardAvoidingView, Platform, Linking } from 'react-native';
import { registerUser } from '../api/auth';
import { fetchClasses } from '../api/classes';
import { Ionicons } from '@expo/vector-icons';
import axios from 'axios';
import { API_URL, BASE_URL } from '../api/config';

const RegisterScreen = ({ navigation }) => {
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [mobile, setMobile] = useState('');
    const [password, setPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);

    // New Fields
    const [schoolName, setSchoolName] = useState('');
    const [selectedBoard, setSelectedBoard] = useState('CBSE'); // Default to CBSE
    const [selectedClass, setSelectedClass] = useState(null); // {class_id, class_name}

    // Class Data
    const [classes, setClasses] = useState([]);
    const [loadingClasses, setLoadingClasses] = useState(false);
    const [showClassModal, setShowClassModal] = useState(false);

    // Loading state is already defined above
    const [loading, setLoading] = useState(false);



    // Reset Classes when board changes
    useEffect(() => {
        if (selectedBoard) {
            loadClassesData(selectedBoard);
            setSelectedClass(null); // Reset class selection
        } else {
            setClasses([]);
        }
    }, [selectedBoard]);

    const loadClassesData = async (board) => {
        setLoadingClasses(true);
        try {
            // Fetch classes using the optimized service with caching
            const response = await fetchClasses(board);
            if (response && (response.status === 'success' || Array.isArray(response))) {
                const classData = response.data || response;
                setClasses(classData);
            } else {
                setClasses([]); // No classes for this board yet
            }
        } catch (error) {
            console.error("Failed to load classes", error);
            setClasses([]);
        } finally {
            setLoadingClasses(false);
        }
    };

    const handleRegister = async () => {
        // Trim inputs
        const trimmedName = name.trim();
        const trimmedEmail = email.trim();
        const trimmedMobile = mobile.trim();
        const trimmedPassword = password.trim();
        const trimmedSchool = schoolName.trim();

        if (!trimmedName || !trimmedEmail || !trimmedMobile || !trimmedPassword || !trimmedSchool || !selectedBoard || !selectedClass) {
            Alert.alert('Error', 'Please fill in all fields');
            return;
        }

        if (trimmedMobile.length !== 10) {
            Alert.alert('Error', 'Mobile number must be exactly 10 digits');
            return;
        }

        if (trimmedPassword !== confirmPassword) {
            Alert.alert('Error', 'Passwords do not match');
            return;
        }

        if (trimmedPassword.length < 6) {
            Alert.alert('Error', 'Password must be at least 6 characters long');
            return;
        }

        setLoading(true);
        try {
            const data = await registerUser(
                trimmedName,
                trimmedEmail,
                trimmedMobile,
                trimmedPassword,
                trimmedSchool,
                selectedClass.class_id,
                selectedBoard
            );

            setLoading(false);

            if (data && data.status === 'success') {
                Alert.alert('Success', 'Registration successful! Please login.');
                navigation.navigate('Login');
            } else {
                const msg = data?.message || 'Registration failed';
                Alert.alert('Failure', msg);
            }
        } catch (error) {
            setLoading(false);
            const errorMessage = String(error?.message || error || 'An unexpected error occurred');
            console.error("Registration Error:", errorMessage);
            Alert.alert('Registration Error', errorMessage);
        }
    };

    const renderClassItem = ({ item }) => (
        <TouchableOpacity
            style={styles.classItem}
            onPress={() => {
                if (item) {
                    setSelectedClass(item);
                    setShowClassModal(false);
                }
            }}
        >
            <Text style={styles.classItemText}>{item?.class_name || 'Unknown Class'}</Text>
            {selectedClass?.class_id === item?.class_id && <Ionicons name="checkmark" size={20} color="#4f46e5" />}
        </TouchableOpacity>
    );

    return (
        <View style={{ flex: 1, backgroundColor: '#f5f7fa' }}>
            <KeyboardAvoidingView
                behavior={Platform.OS === "ios" ? "padding" : "height"}
                style={{ flex: 1 }}
            >
                <ScrollView contentContainerStyle={styles.container} keyboardShouldPersistTaps="handled">
                    <StatusBar barStyle="light-content" />
                    <View style={[styles.header, { backgroundColor: '#4f46e5' }]}>
                        <Text style={styles.headerTitle}>Create Account</Text>
                        <Text style={styles.headerSubtitle}>Join Veeru and Learn Smarter</Text>
                    </View>

                    <View style={styles.formContainer}>
                        <View style={styles.form}>
                            <Text style={styles.label}>FULL NAME</Text>
                            <TextInput
                                style={styles.input}
                                placeholder="John Doe"
                                value={name}
                                onChangeText={setName}
                                autoCapitalize="words"
                            />

                            <Text style={styles.label}>EMAIL ADDRESS</Text>
                            <TextInput
                                style={styles.input}
                                placeholder="student@example.com"
                                value={email}
                                onChangeText={setEmail}
                                keyboardType="email-address"
                                autoCapitalize="none"
                            />

                            <Text style={styles.label}>MOBILE NUMBER <Text style={{ color: 'red' }}>*</Text></Text>
                            <TextInput
                                style={styles.input}
                                placeholder="9876543210"
                                value={mobile}
                                onChangeText={setMobile}
                                keyboardType="phone-pad"
                                maxLength={10}
                            />

                            <Text style={styles.label}>SCHOOL NAME</Text>
                            <TextInput
                                style={styles.input}
                                placeholder="Enter your school name"
                                value={schoolName}
                                onChangeText={setSchoolName}
                            />

                            {/* Board Selection */}
                            <Text style={styles.label}>SELECT BOARD / MEDIUM</Text>
                            <View style={styles.boardContainer}>
                                {[
                                    { id: 'CBSE', label: 'CBSE' },
                                    { id: 'STATE_MARATHI', label: 'State (Marathi)' },
                                    { id: 'STATE_SEMI', label: 'State (Semi)' }
                                ].map((board) => (
                                    <TouchableOpacity
                                        key={board.id}
                                        style={[styles.boardBtn, selectedBoard === board.id && styles.boardBtnActive]}
                                        onPress={() => setSelectedBoard(board.id)}
                                    >
                                        <Text style={[styles.boardText, selectedBoard === board.id && styles.boardTextActive, { textAlign: 'center', fontSize: 12 }]}>
                                            {board.label}
                                        </Text>
                                    </TouchableOpacity>
                                ))}
                            </View>

                            {/* Class Selection */}
                            <Text style={styles.label}>CLASS</Text>
                            <TouchableOpacity
                                style={[styles.dropdownBtn, !selectedBoard && { opacity: 0.5, backgroundColor: '#f3f4f6' }]}
                                onPress={() => {
                                    if (!selectedBoard) {
                                        Alert.alert("Select Board First", "Please select a board to see available classes.");
                                        return;
                                    }
                                    setShowClassModal(true);
                                }}
                                disabled={!selectedBoard}
                            >
                                <Text style={[styles.dropdownText, !selectedClass && { color: '#9ca3af' }]}>
                                    {selectedClass ? selectedClass.class_name : (selectedBoard ? "Select your class" : "Select Board first")}
                                </Text>
                                <Ionicons name="chevron-down" size={20} color="#6b7280" />
                            </TouchableOpacity>

                            <Text style={styles.label}>PASSWORD</Text>
                            <View style={styles.passwordContainer}>
                                <TextInput
                                    style={styles.passwordInput}
                                    placeholder="Create a password"
                                    value={password}
                                    onChangeText={setPassword}
                                    secureTextEntry={!showPassword}
                                />
                                <TouchableOpacity onPress={() => setShowPassword(!showPassword)} style={styles.eyeIcon}>
                                    <Ionicons name={showPassword ? "eye" : "eye-off"} size={22} color="#6b7280" />
                                </TouchableOpacity>
                            </View>

                            <Text style={styles.label}>CONFIRM PASSWORD</Text>
                            <View style={styles.passwordContainer}>
                                <TextInput
                                    style={styles.passwordInput}
                                    placeholder="Confirm password"
                                    value={confirmPassword}
                                    onChangeText={setConfirmPassword}
                                    secureTextEntry={!showConfirmPassword}
                                />
                                <TouchableOpacity onPress={() => setShowConfirmPassword(!showConfirmPassword)} style={styles.eyeIcon}>
                                    <Ionicons name={showConfirmPassword ? "eye" : "eye-off"} size={22} color="#6b7280" />
                                </TouchableOpacity>
                            </View>

                            <TouchableOpacity
                                style={styles.button}
                                onPress={handleRegister}
                                disabled={loading}
                            >
                                {loading ? (
                                    <ActivityIndicator color="#fff" />
                                ) : (
                                    <Text style={styles.buttonText}>Register</Text>
                                )}
                            </TouchableOpacity>

                            <TouchableOpacity onPress={() => navigation.navigate('Login')} style={styles.loginLink}>
                                <Text style={styles.loginText}>Already have an account? <Text style={styles.loginHighlight}>Login</Text></Text>
                            </TouchableOpacity>

                            <TouchableOpacity
                                onPress={() => Linking.openURL(`${BASE_URL}/privacy-policy.html`)}
                                style={{ marginTop: 20, alignItems: 'center' }}
                            >
                                <Text style={{ color: '#666', fontSize: 12, textAlign: 'center' }}>
                                    By registering, you agree to our {"\n"}
                                    <Text style={{ color: '#4f46e5', textDecorationLine: 'underline' }}>Privacy Policy</Text> and <Text style={{ color: '#4f46e5', textDecorationLine: 'underline' }}>Terms of Service</Text>
                                </Text>
                            </TouchableOpacity>
                        </View>
                    </View>
                </ScrollView>
            </KeyboardAvoidingView>

            {/* Class Selection Modal - Moved Outside ScrollView */}
            <Modal
                visible={showClassModal}
                transparent={true}
                animationType="slide"
                onRequestClose={() => setShowClassModal(false)}
            >
                <View style={styles.modalOverlay}>
                    <View style={styles.modalContent}>
                        <View style={styles.modalHeader}>
                            <Text style={styles.modalTitle}>Select Class</Text>
                            <TouchableOpacity onPress={() => setShowClassModal(false)}>
                                <Ionicons name="close" size={24} color="#333" />
                            </TouchableOpacity>
                        </View>
                        {loadingClasses ? (
                            <ActivityIndicator size="large" color="#4f46e5" style={{ margin: 20 }} />
                        ) : (
                            <FlatList
                                data={Array.isArray(classes) ? classes : []}
                                renderItem={renderClassItem}
                                keyExtractor={(item, index) => item?.class_id ? item.class_id.toString() : index.toString()}
                                style={{ maxHeight: 300 }}
                            />
                        )}
                    </View>
                </View>
            </Modal>
        </View>
    );
};

const styles = StyleSheet.create({
    container: {
        flexGrow: 1,
        backgroundColor: '#f5f7fa',
        paddingBottom: 40
    },
    header: {
        paddingTop: 80,
        paddingBottom: 50,
        paddingHorizontal: 24,
        borderBottomLeftRadius: 30,
        borderBottomRightRadius: 30,
    },
    headerTitle: {
        fontSize: 32,
        fontWeight: 'bold',
        color: '#fff',
        marginBottom: 8,
        fontFamily: 'NotoSans-Bold',
    },
    headerSubtitle: {
        fontSize: 16,
        color: 'rgba(255, 255, 255, 0.9)',
        fontFamily: 'NotoSans-Regular',
    },
    formContainer: {
        flex: 1,
        paddingHorizontal: 24,
        marginTop: -30,
    },
    form: {
        backgroundColor: '#fff',
        padding: 24,
        borderRadius: 20,
        elevation: 5,
        marginBottom: 30,
        paddingTop: 10
    },
    label: {
        fontSize: 14,
        fontWeight: '600',
        color: '#374151',
        marginBottom: 8,
        marginTop: 16,
        fontFamily: 'NotoSans-Bold',
    },
    input: {
        backgroundColor: '#f9fafb',
        borderWidth: 1,
        borderColor: '#e5e7eb',
        borderRadius: 12,
        padding: 16,
        fontSize: 16,
        color: '#1f2937',
        fontFamily: 'NotoSans-Regular',
    },
    passwordContainer: {
        backgroundColor: '#f9fafb',
        borderWidth: 1,
        borderColor: '#e5e7eb',
        borderRadius: 12,
        flexDirection: 'row',
        alignItems: 'center',
        paddingRight: 12,
    },
    passwordInput: {
        flex: 1,
        padding: 16,
        fontSize: 16,
        color: '#1f2937',
        fontFamily: 'NotoSans-Regular',
    },
    eyeIcon: {
        padding: 4,
    },
    boardContainer: {
        flexDirection: 'row',
        gap: 12,
    },
    boardBtn: {
        flex: 1,
        padding: 14,
        borderRadius: 12,
        borderWidth: 1,
        borderColor: '#e5e7eb',
        backgroundColor: '#f9fafb',
        alignItems: 'center',
    },
    boardBtnActive: {
        backgroundColor: '#e0e7ff',
        borderColor: '#4f46e5',
        borderWidth: 2,
    },
    boardText: {
        color: '#6b7280',
        fontWeight: '600',
        fontFamily: 'NotoSans-Bold',
    },
    boardTextActive: {
        color: '#4f46e5',
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    dropdownBtn: {
        backgroundColor: '#f9fafb',
        borderWidth: 1,
        borderColor: '#e5e7eb',
        borderRadius: 12,
        padding: 16,
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
    },
    dropdownText: {
        fontSize: 16,
        color: '#1f2937',
        fontFamily: 'NotoSans-Regular',
    },
    button: {
        backgroundColor: '#4f46e5',
        paddingVertical: 18,
        borderRadius: 14,
        alignItems: 'center',
        marginTop: 32,
        shadowColor: '#4f46e5',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.2,
        shadowRadius: 8,
        elevation: 4,
    },
    buttonText: {
        color: '#fff',
        fontSize: 18,
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    loginLink: {
        marginTop: 20,
        alignItems: 'center',
        padding: 10,
    },
    loginText: {
        color: '#6b7280',
        fontSize: 15,
    },
    loginHighlight: {
        color: '#4f46e5',
        fontWeight: 'bold',
    },
    modalOverlay: {
        flex: 1,
        backgroundColor: 'rgba(0,0,0,0.5)',
        justifyContent: 'center',
        padding: 24,
    },
    modalContent: {
        backgroundColor: 'white',
        borderRadius: 20,
        maxHeight: '60%',
        paddingBottom: 20
    },
    modalHeader: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        padding: 20,
        borderBottomWidth: 1,
        borderBottomColor: '#f3f4f6',
    },
    modalTitle: {
        fontSize: 18,
        fontWeight: 'bold',
        color: '#111827',
    },
    classItem: {
        padding: 16,
        borderBottomWidth: 1,
        borderBottomColor: '#f3f4f6',
        flexDirection: 'row',
        justifyContent: 'space-between',
    },
    classItemText: {
        fontSize: 16,
        color: '#374151',
    }
});

export default RegisterScreen;
