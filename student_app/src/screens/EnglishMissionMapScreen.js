import React, { useState, useEffect, useCallback } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView, ActivityIndicator, Alert, Image, RefreshControl } from 'react-native';
import { useTheme } from '../context/ThemeContext';
import { useFocusEffect } from '@react-navigation/native';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import axios from 'axios';
import { API_URL } from '../api/config';

const EnglishMissionMapScreen = ({ navigation, user }) => {
    const { theme } = useTheme();
    const [missions, setMissions] = useState([]);
    const [loading, setLoading] = useState(true);

    const [refreshing, setRefreshing] = useState(false);

    const onRefresh = useCallback(() => {
        setRefreshing(true);
        fetchMissions().then(() => setRefreshing(false));
    }, [user]);

    useFocusEffect(
        useCallback(() => {
            if (user?.user_id) {
                fetchMissions();
            } else {
                console.log("Waiting for user_id to load missions");
            }
        }, [user])
    );

    const fetchMissions = async () => {
        try {
            const response = await axios.get(`${API_URL}/get_english_missions.php?user_id=${user.user_id}`);
            if (response.data.status === 'success') {
                setMissions(response.data.data);
            }
        } catch (error) {
            console.error(error);
            Alert.alert('Error', 'Failed to load missions');
        } finally {
            setLoading(false);
        }
    };

    const handleMissionPress = (mission) => {
        if (mission.is_locked) {
            Alert.alert('Locked 🔒', 'Complete the previous mission to unlock this one!');
            return;
        }
        navigation.navigate('EnglishTutor', { mission });
    };

    if (loading) {
        return (
            <View style={[styles.container, styles.center, { backgroundColor: theme.background }]}>
                <ActivityIndicator size="large" color={theme.primary} />
            </View>
        );
    }

    return (
        <View style={[styles.container, { backgroundColor: theme.background }]}>
            <LinearGradient colors={['#4f46e5', '#818cf8']} style={styles.header}>
                <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                    <Ionicons name="arrow-back" size={24} color="#fff" />
                </TouchableOpacity>
                <Text style={styles.headerTitle}>English Missions 🗺️</Text>
            </LinearGradient>

            <ScrollView
                contentContainerStyle={styles.mapContainer}
                refreshControl={
                    <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[theme.primary]} />
                }
            >
                <View style={styles.pathLine} />
                {missions.map((mission, index) => {
                    // Zig-zag pattern
                    const isLeft = index % 2 === 0;
                    return (
                        <View key={mission.level_id} style={[styles.levelRow, isLeft ? styles.rowLeft : styles.rowRight]}>
                            <TouchableOpacity
                                activeOpacity={0.9}
                                onPress={() => handleMissionPress(mission)}
                                style={[
                                    styles.missionCircle,
                                    mission.is_locked && styles.lockedCircle,
                                    mission.is_completed && styles.completedCircle
                                ]}
                            >
                                {mission.is_locked ? (
                                    <Ionicons name="lock-closed" size={32} color="#94a3b8" />
                                ) : (
                                    <Text style={styles.levelNumber}>{mission.level_id}</Text>
                                )}

                                {mission.is_completed && (
                                    <View style={styles.starsContainer}>
                                        <Text>⭐⭐⭐</Text>
                                    </View>
                                )}
                            </TouchableOpacity>

                            <View style={styles.labelContainer}>
                                <Text style={[styles.levelTitle, { color: theme.text }]}>{mission.title}</Text>
                                <Text style={[styles.levelRole, { color: theme.textSecondary }]}>{mission.role}</Text>
                            </View>
                        </View>
                    );
                })}
            </ScrollView>
        </View>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1 },
    center: { justifyContent: 'center', alignItems: 'center' },
    header: { padding: 20, paddingTop: 50, flexDirection: 'row', alignItems: 'center', elevation: 4 },
    backButton: { marginRight: 15 },
    headerTitle: { fontSize: 24, fontWeight: 'bold', color: '#fff' },
    mapContainer: { padding: 20, paddingBottom: 50, alignItems: 'center' },
    pathLine: {
        position: 'absolute',
        top: 0,
        bottom: 0,
        width: 4,
        backgroundColor: '#e2e8f0',
        zIndex: -1
    },
    levelRow: {
        width: '100%',
        alignItems: 'center',
        marginBottom: 40,
        position: 'relative'
    },
    rowLeft: { alignSelf: 'flex-start', paddingRight: '30%' },
    rowRight: { alignSelf: 'flex-end', paddingLeft: '30%' },
    missionCircle: {
        width: 80,
        height: 80,
        borderRadius: 40,
        backgroundColor: '#fbbf24', // Active yellow
        justifyContent: 'center',
        alignItems: 'center',
        borderWidth: 4,
        borderColor: '#fff',
        elevation: 8,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.2,
        shadowRadius: 4,
    },
    lockedCircle: {
        backgroundColor: '#e2e8f0',
        borderColor: '#cbd5e1',
        elevation: 0
    },
    completedCircle: {
        backgroundColor: '#22c55e', // Green
        borderColor: '#86efac'
    },
    levelNumber: {
        fontSize: 32,
        fontWeight: 'bold',
        color: '#fff'
    },
    starsContainer: {
        position: 'absolute',
        bottom: -15,
        backgroundColor: '#fff',
        paddingHorizontal: 8,
        paddingVertical: 2,
        borderRadius: 10,
        elevation: 2
    },
    labelContainer: {
        marginTop: 10,
        alignItems: 'center',
        backgroundColor: 'rgba(255,255,255,0.9)',
        padding: 5,
        borderRadius: 8
    },
    levelTitle: { fontWeight: 'bold', fontSize: 14 },
    levelRole: { fontSize: 12 }
});

export default EnglishMissionMapScreen;
