import React, { useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Image, ScrollView, ActivityIndicator, Alert, StatusBar, Dimensions } from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { useTheme } from '../context/ThemeContext';
import { uploadHomeworkImage } from '../api/ai';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';

const { width } = Dimensions.get('window');

const HomeworkSolverScreen = ({ navigation }) => {
    const { theme } = useTheme();
    const [image, setImage] = useState(null);
    const [solution, setSolution] = useState('');
    const [loading, setLoading] = useState(false);

    const pickImage = async () => {
        const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
        if (status !== 'granted') {
            Alert.alert('Permission needed', 'Sorry, we need camera roll permissions to make this work!');
            return;
        }

        let result = await ImagePicker.launchImageLibraryAsync({
            mediaTypes: ImagePicker.MediaTypeOptions.Images,
            allowsEditing: true,
            quality: 1,
        });

        if (!result.canceled) {
            setImage(result.assets[0].uri);
            setSolution('');
        }
    };

    const takePhoto = async () => {
        const { status } = await ImagePicker.requestCameraPermissionsAsync();
        if (status !== 'granted') {
            Alert.alert('Permission needed', 'Sorry, we need camera permissions to make this work!');
            return;
        }

        let result = await ImagePicker.launchCameraAsync({
            allowsEditing: true,
            quality: 1,
        });

        if (!result.canceled) {
            setImage(result.assets[0].uri);
            setSolution('');
        }
    };

    const handleSolve = async () => {
        if (!image) return;

        setLoading(true);
        const response = await uploadHomeworkImage(image);
        setLoading(false);

        if (response.status === 'success') {
            setSolution(response.reply);
        } else {
            Alert.alert('Error', 'Failed to analyze the image. Please try again.');
        }
    };

    return (
        <View style={styles.container}>
            <StatusBar barStyle="light-content" backgroundColor="#be185d" />

            {/* Header */}
            <View style={styles.headerContainer}>
                <LinearGradient
                    colors={['#be185d', '#db2777']}
                    style={styles.headerBackground}
                >
                    <View style={styles.headerContent}>
                        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                            <Ionicons name="arrow-back" size={24} color="#fff" />
                        </TouchableOpacity>
                        <Text style={styles.headerTitle}>Homework Solver</Text>
                    </View>
                </LinearGradient>
            </View>

            <ScrollView style={styles.content} contentContainerStyle={{ paddingBottom: 40 }} showsVerticalScrollIndicator={false}>
                {/* Image Preview & Action Area */}
                <View style={styles.card}>
                    <View style={styles.imageWrapper}>
                        {image ? (
                            <Image source={{ uri: image }} style={styles.previewImage} resizeMode="contain" />
                        ) : (
                            <View style={styles.placeholderContainer}>
                                <View style={styles.iconCircle}>
                                    <Ionicons name="camera" size={40} color="#db2777" />
                                </View>
                                <Text style={styles.placeholderTitle}>Snap a Photo</Text>
                                <Text style={styles.placeholderText}>
                                    Take a clear picture of your homework question to get instant help.
                                </Text>
                            </View>
                        )}

                        {image && (
                            <TouchableOpacity style={styles.closeButton} onPress={() => { setImage(null); setSolution(''); }}>
                                <Ionicons name="close" size={20} color="#fff" />
                            </TouchableOpacity>
                        )}
                    </View>

                    {!image && (
                        <View style={styles.buttonRow}>
                            <TouchableOpacity style={styles.actionButton} onPress={takePhoto}>
                                <LinearGradient colors={['#fce7f3', '#fbcfe8']} style={styles.buttonGradient}>
                                    <Ionicons name="camera" size={24} color="#db2777" />
                                    <Text style={styles.buttonText}>Camera</Text>
                                </LinearGradient>
                            </TouchableOpacity>
                            <TouchableOpacity style={styles.actionButton} onPress={pickImage}>
                                <LinearGradient colors={['#e0f2fe', '#bae6fd']} style={styles.buttonGradient}>
                                    <Ionicons name="images" size={24} color="#0284c7" />
                                    <Text style={[styles.buttonText, { color: '#0284c7' }]}>Gallery</Text>
                                </LinearGradient>
                            </TouchableOpacity>
                        </View>
                    )}
                </View>

                {/* Solve Button */}
                {image && (
                    <TouchableOpacity
                        style={[styles.solveButton, { opacity: loading ? 0.7 : 1 }]}
                        onPress={handleSolve}
                        disabled={loading}
                    >
                        <LinearGradient
                            colors={['#db2777', '#be185d']}
                            style={styles.solveGradient}
                        >
                            {loading ? (
                                <ActivityIndicator color="#fff" />
                            ) : (
                                <>
                                    <Ionicons name="sparkles" size={24} color="#fff" style={{ marginRight: 10 }} />
                                    <Text style={styles.solveButtonText}>Solve with AI</Text>
                                </>
                            )}
                        </LinearGradient>
                    </TouchableOpacity>
                )}

                {/* Solution Display */}
                {solution ? (
                    <View style={styles.solutionContainer}>
                        <View style={styles.solutionHeader}>
                            <Ionicons name="school" size={24} color="#be185d" />
                            <Text style={styles.solutionTitle}>Step-by-Step Solution</Text>
                        </View>
                        <View style={styles.solutionCard}>
                            <Text style={styles.solutionText}>{solution}</Text>
                        </View>
                    </View>
                ) : null}
            </ScrollView>
        </View>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: '#f8fafc',
    },
    headerContainer: {
        marginBottom: 10,
    },
    headerBackground: {
        paddingTop: 50,
        paddingBottom: 20,
        paddingHorizontal: 20,
        borderBottomLeftRadius: 30,
        borderBottomRightRadius: 30,
    },
    headerContent: {
        flexDirection: 'row',
        alignItems: 'center',
    },
    backButton: {
        marginRight: 15,
        padding: 5,
    },
    headerTitle: {
        fontSize: 22,
        fontWeight: 'bold',
        color: '#fff',
    },
    content: {
        padding: 20,
    },
    card: {
        backgroundColor: '#fff',
        borderRadius: 24,
        padding: 10,
        elevation: 2,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.05,
        shadowRadius: 5,
        marginBottom: 20,
    },
    imageWrapper: {
        height: 300,
        borderRadius: 20,
        backgroundColor: '#fdf2f8',
        overflow: 'hidden',
        borderWidth: 2,
        borderColor: '#fce7f3',
        borderStyle: 'dashed',
    },
    previewImage: {
        width: '100%',
        height: '100%',
        backgroundColor: '#000',
    },
    placeholderContainer: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
        padding: 40,
    },
    iconCircle: {
        width: 80,
        height: 80,
        borderRadius: 40,
        backgroundColor: '#fce7f3',
        justifyContent: 'center',
        alignItems: 'center',
        marginBottom: 20,
    },
    placeholderTitle: {
        fontSize: 20,
        fontWeight: 'bold',
        color: '#be185d',
        marginBottom: 10,
    },
    placeholderText: {
        textAlign: 'center',
        color: '#831843',
        lineHeight: 24,
    },
    closeButton: {
        position: 'absolute',
        top: 10,
        right: 10,
        backgroundColor: 'rgba(0,0,0,0.5)',
        padding: 8,
        borderRadius: 20,
    },
    buttonRow: {
        flexDirection: 'row',
        gap: 12,
        padding: 10,
        marginTop: 10,
    },
    actionButton: {
        flex: 1,
        height: 56,
        borderRadius: 16,
        overflow: 'hidden',
    },
    buttonGradient: {
        flex: 1,
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        gap: 8,
    },
    buttonText: {
        fontSize: 16,
        fontWeight: 'bold',
        color: '#db2777',
    },
    solveButton: {
        borderRadius: 20,
        overflow: 'hidden',
        marginBottom: 30,
        elevation: 4,
        shadowColor: '#db2777',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.3,
        shadowRadius: 10,
    },
    solveGradient: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'center',
        paddingVertical: 18,
    },
    solveButtonText: {
        color: '#fff',
        fontSize: 18,
        fontWeight: 'bold',
    },
    solutionContainer: {
        marginTop: 10,
    },
    solutionHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 16,
        paddingHorizontal: 10,
    },
    solutionTitle: {
        fontSize: 20,
        fontWeight: 'bold',
        color: '#be185d',
        marginLeft: 10,
    },
    solutionCard: {
        backgroundColor: '#fff',
        padding: 24,
        borderRadius: 24,
        elevation: 2,
        borderWidth: 1,
        borderColor: '#f0f9ff',
    },
    solutionText: {
        fontSize: 16,
        lineHeight: 26,
        color: '#334155',
    },
});

export default HomeworkSolverScreen;
