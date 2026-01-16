import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Alert, ActivityIndicator, FlatList, Image } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import AsyncStorage from '@react-native-async-storage/async-storage';
import axios from 'axios';
import { API_URL } from '../api/config';
import { updateStudentClass } from '../api/classes';

const SetupScreen = ({ navigation, route }) => {
    const user = route.params?.user;
    const [step, setStep] = useState(1); // 1: Board, 2: Class
    const [selectedBoard, setSelectedBoard] = useState('CBSE');
    const [selectedClass, setSelectedClass] = useState(null);
    const [classes, setClasses] = useState([]);
    const [loading, setLoading] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (step === 2 && selectedBoard) {
            loadClasses(selectedBoard);
        }
    }, [step, selectedBoard]);

    const loadClasses = async (board) => {
        setLoading(true);
        try {
            const response = await axios.get(`${API_URL}/get_classes.php?board=${board}`);
            if (response.data && response.data.status === 'success') {
                setClasses(response.data.data);
            } else {
                setClasses([]);
            }
        } catch (error) {
            console.error("Failed to load classes:", error);
            Alert.alert("Error", "Failed to load classes. Please try again.");
        } finally {
            setLoading(false);
        }
    };

    const handleContinue = async () => {
        if (step === 1) {
            if (!selectedBoard) {
                Alert.alert("Select Board", "Please select your board to continue.");
                return;
            }
            setStep(2);
        } else if (step === 2) {
            if (!selectedClass) {
                Alert.alert("Select Class", "Please select your class to continue.");
                return;
            }

            // Save and Finish
            setSubmitting(true);
            try {
                const response = await updateStudentClass(user.user_id, selectedClass.class_id, selectedBoard);
                if (response.status === 'success') {
                    // Update Local Storage
                    const updatedUser = {
                        ...user,
                        class_id: selectedClass.class_id,
                        class_name: selectedClass.class_name,
                        board_type: selectedBoard
                    };
                    await AsyncStorage.setItem('user_data', JSON.stringify(updatedUser));

                    // Navigate to Home
                    navigation.replace('Main', { user: updatedUser });
                } else {
                    Alert.alert("Error", "Failed to save selection.");
                }
            } catch (error) {
                console.error("Save Error:", error);
                Alert.alert("Error", "Something went wrong. Please try again.");
            } finally {
                setSubmitting(false);
            }
        }
    };

    const renderBoardItem = (board) => (
        <TouchableOpacity
            key={board.id}
            style={[styles.optionCard, selectedBoard === board.id && styles.optionCardActive]}
            onPress={() => setSelectedBoard(board.id)}
        >
            <View style={[styles.optionIcon, selectedBoard === board.id && { backgroundColor: '#4f46e5' }]}>
                {selectedBoard === board.id ? (
                    <Ionicons name="checkmark" size={24} color="#fff" />
                ) : (
                    <Text style={{ fontSize: 24 }}>🎓</Text>
                )}
            </View>
            <Text style={[styles.optionText, selectedBoard === board.id && styles.optionTextActive]}>
                {board.label}
            </Text>
        </TouchableOpacity>
    );

    const renderClassItem = ({ item }) => (
        <TouchableOpacity
            style={[styles.classItem, selectedClass?.class_id === item.class_id && styles.classItemActive]}
            onPress={() => setSelectedClass(item)}
        >
            <Text style={[styles.classText, selectedClass?.class_id === item.class_id && styles.classTextActive]}>
                {item.class_name}
            </Text>
            {selectedClass?.class_id === item.class_id && (
                <Ionicons name="checkmark-circle" size={24} color="#4f46e5" />
            )}
        </TouchableOpacity>
    );

    return (
        <View style={styles.container}>
            <LinearGradient colors={['#4f46e5', '#3b82f6']} style={styles.header}>
                <Text style={styles.headerTitle}>
                    {step === 1 ? "Select Your Board" : "Select Your Class"}
                </Text>
                <Text style={styles.headerSubtitle}>
                    {step === 1
                        ? "Tell us which board you are studying in."
                        : `Great! Showing classes for ${selectedBoard === 'CBSE' ? 'CBSE' : 'State Board'}.`}
                </Text>
            </LinearGradient>

            <View style={styles.content}>
                {step === 1 ? (
                    <View style={styles.optionsContainer}>
                        {[
                            { id: 'CBSE', label: 'CBSE Board' },
                            { id: 'STATE_MARATHI', label: 'State Board (Marathi)' },
                            { id: 'STATE_SEMI', label: 'State Board (Semi)' }
                        ].map(renderBoardItem)}
                    </View>
                ) : (
                    <View style={{ flex: 1 }}>
                        {loading ? (
                            <ActivityIndicator size="large" color="#4f46e5" style={{ marginTop: 50 }} />
                        ) : (
                            <FlatList
                                data={classes}
                                renderItem={renderClassItem}
                                keyExtractor={item => item.class_id.toString()}
                                contentContainerStyle={{ paddingBottom: 20 }}
                                showsVerticalScrollIndicator={false}
                                ListEmptyComponent={
                                    <Text style={{ textAlign: 'center', marginTop: 50, color: '#666' }}>
                                        No classes found for this board.
                                    </Text>
                                }
                            />
                        )}
                        <TouchableOpacity onPress={() => setStep(1)} style={styles.backButton}>
                            <Text style={styles.backButtonText}>Change Board</Text>
                        </TouchableOpacity>
                    </View>
                )}

                <TouchableOpacity
                    style={[
                        styles.continueButton,
                        ((step === 1 && !selectedBoard) || (step === 2 && !selectedClass)) && styles.disabledButton
                    ]}
                    onPress={handleContinue}
                    disabled={(step === 1 && !selectedBoard) || (step === 2 && !selectedClass) || submitting}
                >
                    {submitting ? (
                        <ActivityIndicator color="#fff" />
                    ) : (
                        <Text style={styles.continueButtonText}>Continue</Text>
                    )}
                </TouchableOpacity>
            </View>
        </View>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: '#f5f7fa',
    },
    header: {
        paddingTop: 80,
        paddingBottom: 40,
        paddingHorizontal: 24,
        borderBottomLeftRadius: 30,
        borderBottomRightRadius: 30,
    },
    headerTitle: {
        fontSize: 28,
        fontWeight: 'bold',
        color: '#fff',
        marginBottom: 8,
    },
    headerSubtitle: {
        fontSize: 16,
        color: 'rgba(255, 255, 255, 0.9)',
    },
    content: {
        flex: 1,
        padding: 24,
        marginTop: -20,
        backgroundColor: '#f5f7fa', // Matches container bg
        borderTopLeftRadius: 20,
        borderTopRightRadius: 20,
    },
    optionsContainer: {
        gap: 16,
    },
    optionCard: {
        backgroundColor: '#fff',
        padding: 20,
        borderRadius: 16,
        flexDirection: 'row',
        alignItems: 'center',
        elevation: 2,
        borderWidth: 1,
        borderColor: 'transparent',
    },
    optionCardActive: {
        borderColor: '#4f46e5',
        backgroundColor: '#eef2ff',
    },
    optionIcon: {
        width: 50,
        height: 50,
        borderRadius: 25,
        backgroundColor: '#f3f4f6',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 16,
    },
    optionText: {
        fontSize: 18,
        fontWeight: '600',
        color: '#374151',
    },
    optionTextActive: {
        color: '#4f46e5',
        fontWeight: 'bold',
    },
    classItem: {
        backgroundColor: '#fff',
        padding: 20,
        borderRadius: 16,
        marginBottom: 12,
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        elevation: 1,
        borderWidth: 1,
        borderColor: 'transparent',
    },
    classItemActive: {
        borderColor: '#4f46e5',
        backgroundColor: '#eef2ff',
    },
    classText: {
        fontSize: 16,
        color: '#374151',
        fontWeight: '500',
    },
    classTextActive: {
        color: '#4f46e5',
        fontWeight: 'bold',
    },
    continueButton: {
        backgroundColor: '#4f46e5',
        paddingVertical: 18,
        borderRadius: 16,
        alignItems: 'center',
        marginTop: 20,
        elevation: 4,
    },
    disabledButton: {
        backgroundColor: '#a5b4fc',
        elevation: 0,
    },
    continueButtonText: {
        color: '#fff',
        fontSize: 18,
        fontWeight: 'bold',
    },
    backButton: {
        alignItems: 'center',
        padding: 10,
        marginBottom: 10
    },
    backButtonText: {
        color: '#6b7280',
        fontSize: 14
    }
});

export default SetupScreen;
