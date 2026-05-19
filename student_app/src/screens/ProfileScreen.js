import React, { useState, useEffect, useCallback } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Image, Alert, ActivityIndicator, Switch, ScrollView, Linking, TextInput } from 'react-native';
import { Ionicons } from '@expo/vector-icons';

import AsyncStorage from '@react-native-async-storage/async-storage';
import * as ImagePicker from 'expo-image-picker';
import axios from 'axios';
import config, { API_URL, BASE_URL, ENABLE_PAYMENTS } from '../api/config';
import { fetchClasses, updateStudentClass } from '../api/classes';
import { useTheme } from '../context/ThemeContext';
import { useLanguage } from '../context/LanguageContext';
import BadgesSection from '../components/BadgesSection';
import { Modal, Pressable, FlatList, RefreshControl } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';

const ProfileScreen = ({ user, onLogout, onUserUpdate, navigation }) => {
    const { theme, isDarkMode, toggleTheme } = useTheme();
    const { language, changeLanguage, t } = useLanguage();
    const [profilePic, setProfilePic] = useState(user?.profile_picture);
    const [uploading, setUploading] = useState(false);
    const [modalVisible, setModalVisible] = useState(false);
    const [helpModalVisible, setHelpModalVisible] = useState(false);
    const [securityModalVisible, setSecurityModalVisible] = useState(false); // Security sub-menu
    const [historyModalVisible, setHistoryModalVisible] = useState(false);
    const [joinClassModalVisible, setJoinClassModalVisible] = useState(false);
    const [classCodeInput, setClassCodeInput] = useState('');
    const [joiningClass, setJoiningClass] = useState(false);
    const [examHistory, setExamHistory] = useState([]);
    const [loadingHistory, setLoadingHistory] = useState(false);
    const [classes, setClasses] = useState([]);
    const [currentClassId, setCurrentClassId] = useState(user?.class_id);
    const [currentClassName, setCurrentClassName] = useState(user?.class_name);
    const [currentBoard, setCurrentBoard] = useState(user?.board_type || 'STATE_MARATHI'); // Default to Marathi
    const [loadingClasses, setLoadingClasses] = useState(false);

    const [refreshing, setRefreshing] = useState(false);

    const [fetchingClasses, setFetchingClasses] = useState(false);

    // Pre-warm cache for all boards on mount
    React.useEffect(() => {
        const preWarm = async () => {
            const boards = ['CBSE', 'STATE_MARATHI', 'STATE_SEMI'];
            // Fetch all in parallel without blocking main thread
            Promise.all(boards.map(b => fetchClasses(b))).catch(err => console.log("Pre-warm failed:", err));
        };
        preWarm();
    }, []);

    useFocusEffect(
        React.useCallback(() => {
            loadClasses(currentBoard, true);
        }, [currentBoard])
    );

    const fetchExamHistory = async () => {
        if (!user?.user_id) return;
        setLoadingHistory(true);
        try {
            const res = await axios.get(`${API_URL}/get_exam_history.php?user_id=${user.user_id}`);
            if (res.data.status === 'success') {
                setExamHistory(res.data.data);
            }
        } catch (e) {
            console.log('Failed to fetch history', e);
        } finally {
            setLoadingHistory(false);
        }
    };

    const handleOpenHistory = () => {
        setHistoryModalVisible(true);
        fetchExamHistory();
    };

    const onRefresh = React.useCallback(() => {
        setRefreshing(true);
        loadClasses(currentBoard, true).then(() => setRefreshing(false));
    }, [currentBoard]);

    const loadClasses = async (board, forceRefresh = false) => {
        try {
            setFetchingClasses(true);
            // Use the optimized API which handles caching
            const response = await fetchClasses(board, forceRefresh);

            if (response && (response.status === 'success' || Array.isArray(response))) {
                const classData = response.data || response;
                setClasses(classData);
            } else {
                setClasses([]);
                Alert.alert("Error", response?.message || "Failed to load classes");
            }
        } catch (error) {
            console.error("Failed to load classes:", error);
            Alert.alert("Connection Error", `Could not load classes. Please try again.\n${error.message}`);
            setClasses([]);
        } finally {
            setFetchingClasses(false);
        }
    };

    const handleBoardChange = (board) => {
        if (board === currentBoard) return;
        setCurrentBoard(board);
        // We don't automatically clear class here, we wait for user to select a new one
        // or we could force a reset if the current class isn't in the new board 
        // (logic handled by backend usually, but for UI we just show available classes)
    };

    const handleClassChange = async (newClass) => {
        if (newClass.class_id === currentClassId) return;

        Alert.alert(
            "Change Class",
            `Are you sure you want to change your class to ${newClass.class_name}?`,
            [
                { text: "Cancel", style: "cancel" },
                {
                    text: "Yes, Change",
                    onPress: async () => {
                        const studentId = user?.user_id;

                        if (!studentId) {
                            Alert.alert("Error", "Student ID is missing. Please log in again.");
                            return;
                        }

                        try {
                            setLoadingClasses(true);
                            // Assuming we want to save the board selection along with the class
                            const response = await updateStudentClass(studentId, newClass.class_id, currentBoard);

                            if (response.status === 'success') {
                                setCurrentClassId(newClass.class_id);
                                setCurrentClassName(newClass.class_name);

                                // Update Async Storage
                                const storedUser = await AsyncStorage.getItem('user_data');
                                if (storedUser) {
                                    const parsedUser = JSON.parse(storedUser);
                                    parsedUser.class_id = newClass.class_id;
                                    parsedUser.class_name = newClass.class_name;
                                    parsedUser.board_type = currentBoard; // Save the board too
                                    await AsyncStorage.setItem('user_data', JSON.stringify(parsedUser));
                                }

                                // Update MainScreen state to trigger re-renders in Home/Subjects
                                if (onUserUpdate) {
                                    onUserUpdate({
                                        class_id: newClass.class_id,
                                        class_name: newClass.class_name,
                                        board_type: currentBoard
                                    });
                                }

                                Alert.alert("Success", "Class updated successfully!");
                            } else {
                                Alert.alert("Error", response.message || "Failed to update class.");
                            }
                        } catch (error) {
                            console.error("Update Class Error:", error);
                            Alert.alert("Error", error.message || "Something went wrong.");
                        } finally {
                            setLoadingClasses(false);
                        }
                    }
                }
            ]
        );
    };

    const handleJoinClass = async () => {
        if (!classCodeInput || classCodeInput.trim().length !== 6) {
            Alert.alert('Invalid Code', 'Please enter a valid 6-character class code.');
            return;
        }

        if (!user?.user_id) return;

        setJoiningClass(true);
        try {
            const response = await axios.post(`${API_URL}/student/join_classroom.php`, {
                student_id: user.user_id,
                class_code: classCodeInput.trim().toUpperCase()
            });

            if (response.data.status === 'success') {
                const { school_name, class_id, class_name, division_name } = response.data.data;
                
                // Update local state
                setCurrentClassId(class_id);
                setCurrentClassName(class_name);
                
                // Update AsyncStorage
                const storedUser = await AsyncStorage.getItem('user_data');
                if (storedUser) {
                    const parsedUser = JSON.parse(storedUser);
                    parsedUser.class_id = class_id;
                    parsedUser.class_name = class_name;
                    parsedUser.school_name = school_name;
                    parsedUser.division_name = division_name;
                    parsedUser.subscription_status = 'active';
                    await AsyncStorage.setItem('user_data', JSON.stringify(parsedUser));
                }
                
                // Update parent context
                if (onUserUpdate) {
                    onUserUpdate({ 
                        class_id, 
                        class_name, 
                        school_name, 
                        division_name, 
                        subscription_status: 'active' 
                    });
                }

                Alert.alert('Success', response.data.message);
                setJoinClassModalVisible(false);
                setClassCodeInput('');
            } else {
                Alert.alert('Error', response.data.message || 'Failed to join class.');
            }
        } catch (error) {
            console.error('Join Class Error:', error);
            Alert.alert('Error', error.response?.data?.message || 'Failed to verify class code.');
        } finally {
            setJoiningClass(false);
        }
    };

    const languages = [
        { code: 'en', name: 'English', icon: '🇬🇧' },
        { code: 'hi', name: 'हिंदी', icon: '🇮🇳' },
        { code: 'mr', name: 'मराठी', icon: '🚩' },
    ];

    const pickImage = async () => {
        // Request permissions
        const permissionResult = await ImagePicker.requestMediaLibraryPermissionsAsync();

        if (permissionResult.granted === false) {
            Alert.alert("Permission Required", "You need to grant camera roll permissions to change your profile picture.");
            return;
        }

        const result = await ImagePicker.launchImageLibraryAsync({
            mediaTypes: ImagePicker.MediaTypeOptions.Images,
            allowsEditing: true,
            aspect: [1, 1],
            quality: 0.5,
        });

        if (!result.canceled) {
            uploadImage(result.assets[0].uri);
        }
    };

    const uploadImage = async (uri) => {
        if (!user?.user_id) return;

        setUploading(true);
        const formData = new FormData();
        formData.append('user_id', user.user_id);
        formData.append('profile_picture', {
            uri: uri,
            type: 'image/jpeg',
            name: 'profile.jpg',
        });

        try {
            const response = await axios.post(`${API_URL}/upload_profile_picture.php`, formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });

            if (response.data.status === 'success') {
                setProfilePic(response.data.data.profile_picture);
                Alert.alert('Success', 'Profile picture updated!');
            } else {
                Alert.alert('Error', response.data.message || 'Failed to upload image');
            }
        } catch (error) {
            console.error('Upload error:', error);
            Alert.alert('Error', 'Failed to upload image. Please try again.');
        } finally {
            setUploading(false);
        }
    };

    const getImageUrl = (path) => {
        if (!path) return null;
        if (path.startsWith('http')) return path;
        return `${BASE_URL}/${path}`;
    };

    const handleDeleteAccount = async () => {
        Alert.alert(
            "Delete Account",
            "Are you sure you want to permanently delete your account and all your progress? This action cannot be undone.",
            [
                { text: "Cancel", style: "cancel" },
                {
                    text: "Delete Permanently",
                    style: "destructive",
                    onPress: async () => {
                        try {
                            setUploading(true);
                            const response = await axios.post(`${API_URL}/delete_account.php`, {
                                user_id: user.user_id
                            });

                            if (response.data.status === 'success') {
                                Alert.alert("Account Deleted", "Your account has been permanently removed.");
                                if (onLogout) onLogout();
                            } else {
                                Alert.alert("Error", response.data.message || "Failed to delete account.");
                            }
                        } catch (error) {
                            Alert.alert("Error", "A server error occurred while deleting your account.");
                        } finally {
                            setUploading(false);
                        }
                    }
                }
            ]
        );
    };

    const PRIVACY_POLICY_URL = `${config.ROOT_URL}/privacy.php`;

    const handleOpenPrivacyPolicy = () => {
        Linking.openURL(PRIVACY_POLICY_URL).catch(err => {
            Alert.alert("Error", "Could not open the privacy policy link.");
        });
    };

    return (
        <View style={{ flex: 1, backgroundColor: theme.background }}>
            <ScrollView
                showsVerticalScrollIndicator={false}
                contentContainerStyle={{ padding: 20, paddingBottom: 40 }}
                refreshControl={
                    <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[theme.primary]} />
                }
            >
                <View style={styles.header}>
                    <TouchableOpacity onPress={pickImage} disabled={uploading}>
                        <View style={[styles.avatarContainer, { backgroundColor: theme.primary }]}>
                            {uploading ? (
                                <ActivityIndicator size="large" color="white" />
                            ) : profilePic ? (
                                <Image
                                    source={{ uri: getImageUrl(profilePic) }}
                                    style={styles.avatarImage}
                                />
                            ) : (
                                <Text style={styles.avatarText}>{user?.name?.charAt(0) || 'S'}</Text>
                            )}
                            <View style={[styles.editIconContainer, { backgroundColor: theme.card }]}>
                                <Text style={styles.editIcon}>📷</Text>
                            </View>
                        </View>
                    </TouchableOpacity>
                    <Text style={[styles.name, { color: theme.text }]}>{user?.name || 'Student Name'}</Text>
                    <Text style={[styles.email, { color: theme.textSecondary }]}>{user?.email || 'student@example.com'}</Text>
                    <View style={[styles.badge, { backgroundColor: isDarkMode ? '#374151' : '#e0e7ff' }]}>
                        <Text style={[styles.badgeText, { color: theme.primary }]}>{currentClassName || 'Class 10'}</Text>
                    </View>

                    {/* Board Selection Tab */}
                    <View style={styles.classSelectorContainer}>
                        <Text style={[styles.sectionTitle, { color: theme.text }]}>SELECT BOARD / MEDIUM</Text>
                        <View style={{ flexDirection: 'row', paddingHorizontal: 5 }}>
                            {[
                                { id: 'CBSE', label: 'CBSE' },
                                { id: 'STATE_MARATHI', label: 'State (Marathi)' },
                                { id: 'STATE_SEMI', label: 'State (Semi)' }
                            ].map((board) => (
                                <TouchableOpacity
                                    key={board.id}
                                    style={[
                                        styles.boardItem,
                                        {
                                            backgroundColor: currentBoard === board.id ? theme.primary : theme.card,
                                            borderColor: theme.border,
                                            borderWidth: currentBoard === board.id ? 0 : 1
                                        }
                                    ]}
                                    onPress={() => handleBoardChange(board.id)}
                                >
                                    <Text style={{
                                        color: currentBoard === board.id ? '#fff' : theme.text,
                                        fontWeight: '600',
                                        fontSize: 12
                                    }}>
                                        {board.label}
                                    </Text>
                                </TouchableOpacity>
                            ))}
                        </View>
                    </View>

                    {/* Class Scroll Tab */}
                    <View style={styles.classSelectorContainer}>
                        <Text style={[styles.sectionTitle, { color: theme.text }]}>{t('selectClass')?.toUpperCase() || 'SELECT CLASS'}</Text>
                        {fetchingClasses && classes.length === 0 ? (
                            <View style={{ height: 50, justifyContent: 'center', alignItems: 'center' }}>
                                <ActivityIndicator size="small" color={theme.primary} />
                            </View>
                        ) : classes.length === 0 ? (
                            <TouchableOpacity 
                                style={{ height: 50, justifyContent: 'center', alignItems: 'center' }} 
                                onPress={() => loadClasses(currentBoard, true)}
                            >
                                <Text style={{ color: theme.primary, fontWeight: 'bold' }}>Tap to Retry Loading Classes</Text>
                            </TouchableOpacity>
                        ) : (
                            <FlatList
                                data={classes}
                                horizontal
                                showsHorizontalScrollIndicator={false}
                                keyExtractor={(item) => item.class_id.toString()}
                                contentContainerStyle={styles.classList}
                                renderItem={({ item }) => (
                                    <TouchableOpacity
                                        style={[
                                            styles.classItem,
                                            {
                                                backgroundColor: item.class_id === currentClassId ? theme.primary : theme.card,
                                                borderColor: theme.border,
                                                borderWidth: item.class_id === currentClassId ? 0 : 1
                                            }
                                        ]}
                                        onPress={() => handleClassChange(item)}
                                        disabled={loadingClasses}
                                    >
                                        <Text style={[
                                            styles.classItemText,
                                            { color: item.class_id === currentClassId ? '#fff' : theme.text }
                                        ]}>
                                            {item.class_name}
                                        </Text>
                                    </TouchableOpacity>
                                )}
                            />
                        )}

                        {/* Join Teacher's Class Button */}
                        <TouchableOpacity 
                            style={{ 
                                marginTop: 15, 
                                backgroundColor: theme.primary + '15', 
                                padding: 12, 
                                borderRadius: 12, 
                                alignItems: 'center',
                                borderWidth: 1,
                                borderColor: theme.primary + '30',
                                flexDirection: 'row',
                                justifyContent: 'center'
                            }}
                            onPress={() => setJoinClassModalVisible(true)}
                        >
                            <Ionicons name="school" size={18} color={theme.primary} style={{ marginRight: 8 }} />
                            <Text style={{ color: theme.primary, fontWeight: 'bold' }}>Join a Teacher's Class</Text>
                        </TouchableOpacity>
                    </View>
                </View>

                <BadgesSection user={user} />

                <View style={[styles.menu, { backgroundColor: theme.card }]}>
                    <View style={[styles.menuItem, { borderBottomColor: theme.border }]}>
                        <Text style={[styles.menuText, { color: theme.text }]}>{t('darkMode')}</Text>
                        <Switch
                            trackColor={{ false: "#767577", true: theme.primary }}
                            thumbColor={isDarkMode ? "#f4f3f4" : "#f4f3f4"}
                            ios_backgroundColor="#3e3e3e"
                            onValueChange={toggleTheme}
                            value={isDarkMode}
                        />
                    </View>
                    <TouchableOpacity style={[styles.menuItem, { borderBottomColor: theme.border }]} onPress={pickImage}>
                        <Text style={[styles.menuText, { color: theme.text }]}>{t('changeProfilePic')}</Text>
                    </TouchableOpacity>

                    <TouchableOpacity
                        style={[styles.menuItem, { borderBottomColor: theme.border }]}
                        onPress={() => setModalVisible(true)}
                    >
                        <Text style={[styles.menuText, { color: theme.text }]}>{t('appLanguage')}</Text>
                        <View style={{ flexDirection: 'row', alignItems: 'center' }}>
                            <Text style={{ color: theme.textSecondary, marginRight: 8 }}>
                                {languages.find(l => l.code === language)?.name}
                            </Text>
                            <Text style={{ color: theme.textSecondary }}>›</Text>
                        </View>
                    </TouchableOpacity>

                    <TouchableOpacity style={[styles.menuItem, { borderBottomColor: theme.border }]}>
                        <Text style={[styles.menuText, { color: theme.text }]}>{t('subscription')}: {user?.subscription_status || 'Active'}</Text>
                    </TouchableOpacity>

                    <TouchableOpacity
                        style={[styles.menuItem, { borderBottomColor: theme.border, opacity: ENABLE_PAYMENTS ? 1 : 0.4 }]}
                        onPress={() => {
                            if (!ENABLE_PAYMENTS) {
                                Alert.alert('Coming Soon! 🚀', 'Premium features are under development. Stay tuned!');
                            } else {
                                navigation.navigate('Subscription');
                            }
                        }}
                    >
                        <View>
                            <Text style={[styles.menuText, { color: theme.primary, fontWeight: 'bold' }]}>
                                💎 {t('upgradePremium') || 'Upgrade to Premium'}
                            </Text>
                            {!ENABLE_PAYMENTS && (
                                <Text style={{ color: theme.textSecondary, fontSize: 11, marginTop: 2 }}>
                                    Coming Soon
                                </Text>
                            )}
                        </View>
                        <Ionicons name="chevron-forward" size={20} color={theme.textSecondary} />
                    </TouchableOpacity>

                    <TouchableOpacity
                        style={[styles.menuItem, { borderBottomColor: theme.border }]}
                        onPress={() => setSecurityModalVisible(true)}
                    >
                        <Text style={[styles.menuText, { color: theme.text }]}>🛡️ SECURITY & PRIVACY</Text>
                        <Ionicons name="chevron-forward" size={18} color={theme.textSecondary} />
                    </TouchableOpacity>

                    <TouchableOpacity
                        style={[styles.menuItem, { borderBottomColor: theme.border }]}
                        onPress={handleOpenHistory}
                    >
                        <Text style={[styles.menuText, { color: theme.text }]}>📋 MY EXAM HISTORY</Text>
                        <Ionicons name="chevron-forward" size={18} color={theme.textSecondary} />
                    </TouchableOpacity>

                    <TouchableOpacity
                        style={[styles.menuItem, { borderBottomColor: theme.border }]}
                        onPress={() => setHelpModalVisible(true)}
                    >
                        <Text style={[styles.menuText, { color: theme.text }]}>{t('helpSupport')?.toUpperCase()}</Text>
                    </TouchableOpacity>
                </View>

                <TouchableOpacity style={styles.logoutButton} onPress={onLogout}>
                    <Text style={styles.logoutText}>{t('logout')}</Text>
                </TouchableOpacity>

                <View style={styles.copyrightContainer}>
                    <Text style={[styles.copyrightText, { color: theme.textSecondary }]}>
                        {t('copyright')}
                    </Text>
                </View>

            </ScrollView>

            <Modal
                animationType="slide"
                transparent={true}
                visible={modalVisible}
                onRequestClose={() => setModalVisible(false)}
            >
                <View style={styles.modalOverlay}>
                    <View style={[styles.modalView, { backgroundColor: theme.card }]}>
                        <Text style={[styles.modalTitle, { color: theme.text }]}>{t('selectLanguage')?.toUpperCase()}</Text>

                        {languages.map((lang) => (
                            <TouchableOpacity
                                key={lang.code}
                                style={[
                                    styles.languageOption,
                                    language === lang.code && { backgroundColor: theme.primary + '20' }
                                ]}
                                onPress={() => {
                                    changeLanguage(lang.code);
                                    setModalVisible(false);
                                }}
                            >
                                <Text style={{ fontSize: 24, marginRight: 12 }}>{lang.icon}</Text>
                                <Text style={[
                                    styles.languageText,
                                    { color: theme.text },
                                    language === lang.code && { color: theme.primary, fontWeight: 'bold' }
                                ]}>
                                    {lang.name}
                                </Text>
                                {language === lang.code && (
                                    <Text style={{ marginLeft: 'auto', color: theme.primary, fontWeight: 'bold' }}>✓</Text>
                                )}
                            </TouchableOpacity>
                        ))}

                        <TouchableOpacity
                            style={[styles.closeButton, { backgroundColor: theme.border }]}
                            onPress={() => setModalVisible(false)}
                        >
                            <Text style={{ color: theme.text }}>{t('cancel')}</Text>
                        </TouchableOpacity>
                    </View>
                </View>
            </Modal>

            {/* Help & Support Modal */}
            <Modal
                animationType="slide"
                transparent={true}
                visible={helpModalVisible}
                onRequestClose={() => setHelpModalVisible(false)}
            >
                <View style={styles.modalOverlay}>
                    <View style={[styles.modalView, { backgroundColor: theme.card }]}>
                        <Text style={[styles.modalTitle, { color: theme.text }]}>HELP & SUPPORT</Text>

                        <View style={{ width: '100%', marginBottom: 20 }}>
                            <Text style={{ color: theme.text, fontSize: 16, marginBottom: 8 }}>Need assistance? Contact us:</Text>

                            <View style={[styles.infoRow, { backgroundColor: theme.background }]}>
                                <Text style={{ fontSize: 20, marginRight: 10 }}>📧</Text>
                                <View>
                                    <Text style={[styles.infoLabel, { color: theme.textSecondary }]}>Email</Text>
                                    <Text style={[styles.infoValue, { color: theme.primary }]}>veeruappmcq@gmail.com</Text>
                                </View>
                            </View>

                            <View style={[styles.infoRow, { backgroundColor: theme.background }]}>
                                <Text style={{ fontSize: 20, marginRight: 10 }}>📞</Text>
                                <View>
                                    <Text style={[styles.infoLabel, { color: theme.textSecondary }]}>Phone</Text>
                                    <Text style={[styles.infoValue, { color: theme.primary }]}>+91 77559 52198</Text>
                                </View>
                            </View>

                            <Text style={{ color: theme.textSecondary, fontSize: 14, textAlign: 'center', marginTop: 10 }}>
                                Available: Mon - Fri, 9:00 AM - 6:00 PM
                            </Text>
                        </View>

                        <TouchableOpacity
                            style={[styles.closeButton, { backgroundColor: theme.primary }]}
                            onPress={() => setHelpModalVisible(false)}
                        >
                            <Text style={{ color: 'white', fontWeight: 'bold' }}>Close</Text>
                        </TouchableOpacity>
                    </View>
                </View>
            </Modal>

            {/* Security & Privacy Modal (Play Store Compliance) */}
            <Modal
                animationType="slide"
                transparent={true}
                visible={securityModalVisible}
                onRequestClose={() => setSecurityModalVisible(false)}
            >
                <View style={styles.modalOverlay}>
                    <View style={[styles.modalView, { backgroundColor: theme.card }]}>
                        <Text style={[styles.modalTitle, { color: theme.text }]}>SECURITY & PRIVACY</Text>

                        <View style={{ width: '100%', marginBottom: 20 }}>
                            <TouchableOpacity
                                style={[styles.infoRow, { backgroundColor: theme.background, paddingVertical: 15, borderRadius: 12 }]}
                                onPress={handleOpenPrivacyPolicy}
                            >
                                <Ionicons name="document-text-outline" size={24} color={theme.primary} style={{ marginRight: 15 }} />
                                <View style={{ flex: 1 }}>
                                    <Text style={[styles.infoValue, { color: theme.text }]}>Privacy Policy</Text>
                                    <Text style={{ color: theme.textSecondary, fontSize: 12 }}>How we handle your data</Text>
                                </View>
                                <Ionicons name="link" size={20} color={theme.textSecondary} />
                            </TouchableOpacity>

                            <View style={{ height: 20 }} />

                            <TouchableOpacity
                                style={[styles.infoRow, { backgroundColor: '#fee2e2', paddingVertical: 15, borderRadius: 12, borderLeftWidth: 4, borderLeftColor: '#ef4444' }]}
                                onPress={handleDeleteAccount}
                            >
                                <Ionicons name="trash-outline" size={24} color="#ef4444" style={{ marginRight: 15 }} />
                                <View style={{ flex: 1 }}>
                                    <Text style={[styles.infoValue, { color: '#b91c1c', fontWeight: 'bold' }]}>Delete Account</Text>
                                    <Text style={{ color: '#ef4444', fontSize: 12 }}>Permanently remove all data</Text>
                                </View>
                            </TouchableOpacity>

                            <Text style={{ color: theme.textSecondary, fontSize: 11, textAlign: 'center', marginTop: 15, fontStyle: 'italic' }}>
                                Warning: Deleting your account is permanent and cannot be undone.
                            </Text>
                        </View>

                        <TouchableOpacity
                            style={[styles.closeButton, { backgroundColor: theme.primary }]}
                            onPress={() => setSecurityModalVisible(false)}
                        >
                            <Text style={{ color: 'white', fontWeight: 'bold' }}>Close</Text>
                        </TouchableOpacity>
                    </View>
                </View>
            </Modal>

            {/* Exam History Modal */}
            <Modal
                animationType="slide"
                transparent={true}
                visible={historyModalVisible}
                onRequestClose={() => setHistoryModalVisible(false)}
            >
                <View style={styles.modalOverlay}>
                    <View style={[styles.modalView, { backgroundColor: theme.card, maxHeight: '80%' }]}>
                        <Text style={[styles.modalTitle, { color: theme.text }]}>MY EXAM HISTORY</Text>
                        
                        {loadingHistory ? (
                            <ActivityIndicator size="large" color={theme.primary} style={{ marginVertical: 20 }} />
                        ) : examHistory.length === 0 ? (
                            <Text style={{ color: theme.textSecondary, marginBottom: 20 }}>No exam history found.</Text>
                        ) : (
                            <FlatList
                                data={examHistory}
                                keyExtractor={(item) => item.id.toString()}
                                style={{ width: '100%', marginBottom: 15 }}
                                renderItem={({ item }) => {
                                    const date = new Date(item.taken_at).toLocaleDateString();
                                    return (
                                        <View style={[styles.infoRow, { backgroundColor: theme.background, flexDirection: 'column', alignItems: 'flex-start', padding: 12 }]}>
                                            <View style={{ flexDirection: 'row', justifyContent: 'space-between', width: '100%', marginBottom: 4 }}>
                                                <Text style={{ fontWeight: 'bold', color: theme.text, flex: 1 }} numberOfLines={1}>{item.subject_names || 'Exam'}</Text>
                                                <Text style={{ color: theme.textSecondary, fontSize: 12 }}>{date}</Text>
                                            </View>
                                            <View style={{ flexDirection: 'row', justifyContent: 'space-between', width: '100%' }}>
                                                <Text style={{ color: '#16a34a', fontWeight: 'bold' }}>{item.percentage}% Score</Text>
                                                <Text style={{ color: theme.textSecondary, fontSize: 12 }}>{item.correct}/{item.total} Correct</Text>
                                            </View>
                                        </View>
                                    );
                                }}
                            />
                        )}

                        <TouchableOpacity
                            style={[styles.closeButton, { backgroundColor: theme.primary }]}
                            onPress={() => setHistoryModalVisible(false)}
                        >
                            <Text style={{ color: 'white', fontWeight: 'bold' }}>Close</Text>
                        </TouchableOpacity>
                    </View>
                </View>
            </Modal>

            {/* Join Class by Code Modal */}
            <Modal
                animationType="slide"
                transparent={true}
                visible={joinClassModalVisible}
                onRequestClose={() => setJoinClassModalVisible(false)}
            >
                <View style={styles.modalOverlay}>
                    <View style={[styles.modalView, { backgroundColor: theme.card }]}>
                        <Text style={[styles.modalTitle, { color: theme.text }]}>JOIN CLASS</Text>
                        <Text style={{ color: theme.textSecondary, marginBottom: 20, textAlign: 'center' }}>
                            Ask your teacher for the 6-character Class Code and enter it below.
                        </Text>

                        <View style={{ width: '100%', marginBottom: 20 }}>
                            <TextInput
                                style={{
                                    borderWidth: 2,
                                    borderColor: theme.primary,
                                    borderRadius: 12,
                                    padding: 15,
                                    fontSize: 24,
                                    fontWeight: 'bold',
                                    textAlign: 'center',
                                    color: theme.text,
                                    backgroundColor: theme.background,
                                    letterSpacing: 5,
                                    textTransform: 'uppercase'
                                }}
                                placeholder="XXXXXX"
                                placeholderTextColor={theme.textSecondary}
                                value={classCodeInput}
                                onChangeText={setClassCodeInput}
                                maxLength={6}
                                autoCapitalize="characters"
                                editable={!joiningClass}
                            />
                        </View>

                        <TouchableOpacity
                            style={[styles.closeButton, { backgroundColor: theme.primary, marginBottom: 10 }]}
                            onPress={handleJoinClass}
                            disabled={joiningClass || classCodeInput.length !== 6}
                        >
                            {joiningClass ? (
                                <ActivityIndicator color="white" />
                            ) : (
                                <Text style={{ color: 'white', fontWeight: 'bold', fontSize: 16 }}>Join Class</Text>
                            )}
                        </TouchableOpacity>

                        <TouchableOpacity
                            style={{ padding: 10 }}
                            onPress={() => setJoinClassModalVisible(false)}
                            disabled={joiningClass}
                        >
                            <Text style={{ color: theme.textSecondary, fontWeight: 'bold' }}>Cancel</Text>
                        </TouchableOpacity>
                    </View>
                </View>
            </Modal>
        </View>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        padding: 20,
    },
    header: {
        alignItems: 'center',
        marginBottom: 30,
        marginTop: 20,
    },
    avatarContainer: {
        width: 100,
        height: 100,
        borderRadius: 50,
        justifyContent: 'center',
        alignItems: 'center',
        marginBottom: 16,
        elevation: 5,
        position: 'relative',
    },
    avatarImage: {
        width: 100,
        height: 100,
        borderRadius: 50,
    },
    avatarText: {
        fontSize: 40,
        fontWeight: 'bold',
        color: 'white',
    },
    editIconContainer: {
        position: 'absolute',
        bottom: 0,
        right: 0,
        borderRadius: 15,
        width: 30,
        height: 30,
        justifyContent: 'center',
        alignItems: 'center',
        elevation: 2,
    },
    editIcon: {
        fontSize: 16,
    },
    name: {
        fontSize: 24,
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    email: {
        fontSize: 16,
        marginBottom: 8,
        fontFamily: 'NotoSans-Regular',
    },
    badge: {
        paddingHorizontal: 12,
        paddingVertical: 4,
        borderRadius: 12,
    },
    badgeText: {
        fontWeight: '600',
        fontFamily: 'NotoSans-Bold',
    },
    classSelectorContainer: {
        marginTop: 20,
        width: '100%',
        paddingHorizontal: 10,
    },
    sectionTitle: {
        fontSize: 16,
        fontWeight: 'bold',
        marginBottom: 10,
        marginLeft: 5,
        fontFamily: 'NotoSans-Bold',
        textTransform: 'uppercase',
    },
    classList: {
        paddingVertical: 5,
        paddingHorizontal: 2,
    },
    classItem: {
        paddingHorizontal: 20,
        paddingVertical: 10,
        borderRadius: 20,
        marginRight: 10,
        elevation: 2,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 1 },
        shadowOpacity: 0.1,
        shadowRadius: 2,
    },
    classItemText: {
        fontWeight: '600',
        fontFamily: 'NotoSans-Bold',
    },
    boardItem: {
        paddingHorizontal: 12, // Reduced padding for better fit
        paddingVertical: 8,
        borderRadius: 15,
        marginRight: 8,
        elevation: 1,
    },
    menu: {
        borderRadius: 16,
        padding: 8,
        marginBottom: 20,
    },
    menuItem: {
        padding: 16,
        borderBottomWidth: 1,
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
    },
    menuText: {
        fontSize: 16,
        fontFamily: 'NotoSans-Regular',
    },
    logoutButton: {
        backgroundColor: '#ef4444',
        padding: 16,
        borderRadius: 12,
        alignItems: 'center',
    },
    logoutText: {
        color: 'white',
        fontWeight: 'bold',
        fontSize: 16,
        fontFamily: 'NotoSans-Bold',
    },
    modalOverlay: {
        flex: 1,
        backgroundColor: 'rgba(0,0,0,0.5)',
        justifyContent: 'center',
        alignItems: 'center',
    },
    modalView: {
        width: '80%',
        borderRadius: 20,
        padding: 20,
        alignItems: 'center',
        elevation: 5,
    },
    modalTitle: {
        fontSize: 20,
        fontWeight: 'bold',
        marginBottom: 20,
    },
    languageOption: {
        flexDirection: 'row',
        alignItems: 'center',
        padding: 15,
        width: '100%',
        borderRadius: 12,
        marginBottom: 8,
    },
    languageText: {
        fontSize: 18,
    },
    closeButton: {
        marginTop: 10,
        padding: 10,
        borderRadius: 10,
        width: '100%',
        alignItems: 'center',
    },
    copyrightContainer: {
        marginTop: 30,
        marginBottom: 20,
        alignItems: 'center',
        justifyContent: 'center',
    },
    copyrightText: {
        fontSize: 12,
        textAlign: 'center',
        opacity: 0.7,
    },
    infoRow: {
        flexDirection: 'row',
        alignItems: 'center',
        padding: 12,
        borderRadius: 12,
        marginBottom: 10,
    },
    infoLabel: {
        fontSize: 12,
        fontWeight: '600',
    },
    infoValue: {
        fontSize: 16,
        fontWeight: 'bold',
    }
});

export default ProfileScreen;
