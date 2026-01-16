import React, { useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Image, Alert, ActivityIndicator, Switch, ScrollView } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as ImagePicker from 'expo-image-picker';
import axios from 'axios';
import { API_URL, BASE_URL } from '../api/config';
import { fetchClasses, updateStudentClass } from '../api/classes';
import { useTheme } from '../context/ThemeContext';
import { useLanguage } from '../context/LanguageContext';
import BadgesSection from '../components/BadgesSection';
import { Modal, Pressable, FlatList } from 'react-native';

const ProfileScreen = ({ user, onLogout, onUserUpdate }) => {
    const { theme, isDarkMode, toggleTheme } = useTheme();
    const { language, changeLanguage, t } = useLanguage();
    const [profilePic, setProfilePic] = useState(user?.profile_picture);
    const [uploading, setUploading] = useState(false);
    const [modalVisible, setModalVisible] = useState(false);
    const [classes, setClasses] = useState([]);
    const [currentClassId, setCurrentClassId] = useState(user?.class_id);
    const [currentClassName, setCurrentClassName] = useState(user?.class_name);
    const [currentBoard, setCurrentBoard] = useState(user?.board_type || 'STATE_MARATHI'); // Default to Marathi
    const [loadingClasses, setLoadingClasses] = useState(false);

    React.useEffect(() => {
        loadClasses(currentBoard);
    }, [currentBoard]);

    const loadClasses = async (board) => {
        try {
            // Fetch classes filtered by board
            const response = await axios.get(`${API_URL}/get_classes.php?board=${board}`);
            if (response.data && response.data.status === 'success') {
                setClasses(response.data.data);
            } else {
                setClasses([]);
            }
        } catch (error) {
            console.error("Failed to load classes:", error);
            setClasses([]);
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
                        try {
                            setLoadingClasses(true);
                            // Assuming we want to save the board selection along with the class
                            const response = await updateStudentClass(user.user_id, newClass.class_id, currentBoard);

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
                                        class_name: newClass.class_name
                                    });
                                }

                                Alert.alert("Success", "Class updated successfully!");
                            } else {
                                Alert.alert("Error", "Failed to update class.");
                            }
                        } catch (error) {
                            Alert.alert("Error", "Something went wrong.");
                        } finally {
                            setLoadingClasses(false);
                        }
                    }
                }
            ]
        );
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

    return (
        <View style={{ flex: 1, backgroundColor: theme.background }}>
            <ScrollView
                showsVerticalScrollIndicator={false}
                contentContainerStyle={{ padding: 20, paddingBottom: 40 }}
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
                        <Text style={[styles.sectionTitle, { color: theme.text }]}>Select Board / Medium</Text>
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
                        <Text style={[styles.sectionTitle, { color: theme.text }]}>{t('selectClass') || 'Select Class'}</Text>
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
                    <TouchableOpacity style={[styles.menuItem, { borderBottomColor: theme.border }]}>
                        <Text style={[styles.menuText, { color: theme.text }]}>{t('helpSupport')}</Text>
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
                        <Text style={[styles.modalTitle, { color: theme.text }]}>{t('selectLanguage')}</Text>

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
    },
    email: {
        fontSize: 16,
        marginBottom: 8,
    },
    badge: {
        paddingHorizontal: 12,
        paddingVertical: 4,
        borderRadius: 12,
    },
    badgeText: {
        fontWeight: '600',
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
    },
    boardItem: {
        paddingHorizontal: 15,
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
    }
});

export default ProfileScreen;
