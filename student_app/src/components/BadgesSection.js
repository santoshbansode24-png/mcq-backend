import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, FlatList, Image, Modal, TouchableOpacity } from 'react-native';
import { useTheme } from '../context/ThemeContext';
import axios from 'axios';
import { API_URL } from '../api/config';

const BadgesSection = ({ user }) => {
    const { theme } = useTheme();
    const [badges, setBadges] = useState([]);
    const [selectedBadge, setSelectedBadge] = useState(null);

    useEffect(() => {
        if (user?.user_id) {
            fetchBadges();
        }
    }, [user]);

    const fetchBadges = async () => {
        try {
            const response = await axios.get(`${API_URL}/get_badges.php?user_id=${user.user_id}`);
            if (response.data.status === 'success') {
                setBadges(response.data.data);
            }
        } catch (error) {
            console.error('Failed to fetch badges', error);
        }
    };

    const renderBadge = ({ item }) => {
        const isEarned = item.earned === 1;
        return (
            <TouchableOpacity
                style={[
                    styles.badgeItem,
                    { backgroundColor: isEarned ? theme.card : theme.background, borderColor: isEarned ? theme.primary : theme.border }
                ]}
                onPress={() => setSelectedBadge(item)}
            >
                <Text style={[styles.badgeIcon, { opacity: isEarned ? 1 : 0.3 }]}>{item.icon}</Text>
                <Text style={[styles.badgeName, { color: theme.text, opacity: isEarned ? 1 : 0.5 }]} numberOfLines={1}>
                    {item.name}
                </Text>
            </TouchableOpacity>
        );
    };

    return (
        <View style={styles.container}>
            <Text style={[styles.title, { color: theme.text }]}>Achievements</Text>
            <FlatList
                data={badges}
                renderItem={renderBadge}
                keyExtractor={item => item.badge_id.toString()}
                horizontal
                showsHorizontalScrollIndicator={false}
                contentContainerStyle={styles.list}
            />

            <Modal
                visible={!!selectedBadge}
                transparent={true}
                animationType="fade"
                onRequestClose={() => setSelectedBadge(null)}
            >
                <View style={styles.modalOverlay}>
                    <View style={[styles.modalContent, { backgroundColor: theme.card }]}>
                        <Text style={styles.modalIcon}>{selectedBadge?.icon}</Text>
                        <Text style={[styles.modalTitle, { color: theme.text }]}>{selectedBadge?.name}</Text>
                        <Text style={[styles.modalDesc, { color: theme.textSecondary }]}>{selectedBadge?.description}</Text>

                        {selectedBadge?.earned === 1 ? (
                            <Text style={[styles.earnedDate, { color: theme.success }]}>
                                Earned on {new Date(selectedBadge.earned_at).toLocaleDateString()}
                            </Text>
                        ) : (
                            <Text style={[styles.lockedText, { color: theme.textSecondary }]}>Locked</Text>
                        )}

                        <TouchableOpacity
                            style={[styles.closeButton, { backgroundColor: theme.primary }]}
                            onPress={() => setSelectedBadge(null)}
                        >
                            <Text style={styles.closeText}>Close</Text>
                        </TouchableOpacity>
                    </View>
                </View>
            </Modal>
        </View>
    );
};

const styles = StyleSheet.create({
    container: {
        marginTop: 20,
        marginBottom: 10,
    },
    title: {
        fontSize: 18,
        fontWeight: 'bold',
        marginBottom: 10,
        paddingHorizontal: 20,
    },
    list: {
        paddingHorizontal: 15,
    },
    badgeItem: {
        width: 100,
        height: 120,
        marginHorizontal: 5,
        borderRadius: 12,
        alignItems: 'center',
        justifyContent: 'center',
        borderWidth: 1,
        padding: 10,
    },
    badgeIcon: {
        fontSize: 40,
        marginBottom: 8,
    },
    badgeName: {
        fontSize: 12,
        fontWeight: '600',
        textAlign: 'center',
    },
    modalOverlay: {
        flex: 1,
        backgroundColor: 'rgba(0,0,0,0.5)',
        justifyContent: 'center',
        alignItems: 'center',
        padding: 20,
    },
    modalContent: {
        width: '80%',
        padding: 24,
        borderRadius: 20,
        alignItems: 'center',
        elevation: 5,
    },
    modalIcon: {
        fontSize: 60,
        marginBottom: 16,
    },
    modalTitle: {
        fontSize: 24,
        fontWeight: 'bold',
        marginBottom: 8,
    },
    modalDesc: {
        fontSize: 16,
        textAlign: 'center',
        marginBottom: 20,
        lineHeight: 22,
    },
    earnedDate: {
        fontSize: 14,
        fontWeight: 'bold',
        marginBottom: 20,
    },
    lockedText: {
        fontSize: 14,
        fontStyle: 'italic',
        marginBottom: 20,
    },
    closeButton: {
        paddingVertical: 12,
        paddingHorizontal: 30,
        borderRadius: 25,
    },
    closeText: {
        color: 'white',
        fontWeight: 'bold',
    }
});

export default BadgesSection;
