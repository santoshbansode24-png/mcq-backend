import React from 'react';
import {
    View,
    Text,
    StyleSheet,
    TouchableOpacity,
    ScrollView,
    StatusBar,
    Dimensions,
    SafeAreaView,
    Platform
} from 'react-native';
import { useTheme } from '../context/ThemeContext';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';

const { width } = Dimensions.get('window');

const AIScreen = ({ navigation }) => {
    const { theme } = useTheme();

    const features = [
        {
            id: 'tutor',
            title: 'AI Tutor',
            subtitle: '24/7 Study Companion',
            description: 'Ask precise questions and get instant, detailed explanations.',
            icon: 'school',
            color1: '#4f46e5',
            color2: '#818cf8',
            screen: 'AITutor'
        },
        {
            id: 'quiz',
            title: 'Quiz Generator',
            subtitle: 'Test Yourself',
            description: 'Generate custom practice quizzes from your study material.',
            icon: 'create',
            color1: '#7c3aed',
            color2: '#a78bfa',
            screen: 'QuizGenerator'
        },
        {
            id: 'homework',
            title: 'Homework Helper',
            subtitle: 'Snap & Solve',
            description: 'Stuck on a problem? Take a photo and get step-by-step help.',
            icon: 'camera',
            color1: '#d946ef',
            color2: '#f0abfc',
            screen: 'HomeworkSolver'
        },
        {
            id: 'english',
            title: 'English Coach',
            subtitle: 'Improve Fluency',
            description: 'Practice conversation and grammar with an AI native speaker.',
            icon: 'chatbubbles',
            color1: '#f43f5e',
            color2: '#fb7185',
            screen: 'EnglishTutor'
        }
    ];

    return (
        <View style={styles.container}>
            {/* Set translucent to true so the background gradient flows behind the status bar */}
            <StatusBar barStyle="light-content" backgroundColor="transparent" translucent />

            <ScrollView
                style={styles.content}
                showsVerticalScrollIndicator={false}
                // Important: bounces={false} prevents the white gap when pulling down
                bounces={false}
            >
                {/* Header Section */}
                <View style={styles.headerContainer}>
                    <LinearGradient
                        colors={['#1e1b4b', '#312e81']}
                        style={styles.headerBackground}
                    >
                        <View style={styles.headerContent}>
                            <View>
                                <Text style={styles.greeting}>AI Learning Hub</Text>
                                <Text style={styles.subGreeting}>Supercharge your studies 🚀</Text>
                            </View>
                            <TouchableOpacity style={styles.profileButton}>
                                <Ionicons name="sparkles" size={24} color="#fbbf24" />
                            </TouchableOpacity>
                        </View>

                        {/* Quick Stats Card */}
                        <View style={styles.statsCard}>
                            <View style={styles.statItem}>
                                <Text style={styles.statNumber}>12</Text>
                                <Text style={styles.statLabel}>Queries</Text>
                            </View>
                            <View style={styles.statDivider} />
                            <View style={styles.statItem}>
                                <Text style={styles.statNumber}>5</Text>
                                <Text style={styles.statLabel}>Quizzes</Text>
                            </View>
                            <View style={styles.statDivider} />
                            <View style={styles.statItem}>
                                <Text style={styles.statNumber}>🔥 3</Text>
                                <Text style={styles.statLabel}>Streak</Text>
                            </View>
                        </View>
                    </LinearGradient>
                </View>

                <View style={styles.scrollContent}>
                    <Text style={styles.sectionTitle}>Tools</Text>

                    <View style={styles.grid}>
                        {features.map((item) => (
                            <TouchableOpacity
                                key={item.id}
                                style={styles.cardContainer}
                                onPress={() => navigation.navigate(item.screen)}
                                activeOpacity={0.8}
                            >
                                <LinearGradient
                                    colors={[item.color1, item.color2]}
                                    start={{ x: 0, y: 0 }}
                                    end={{ x: 1, y: 1 }}
                                    style={styles.cardGradient}
                                >
                                    <View style={styles.iconCircle}>
                                        <Ionicons name={item.icon} size={28} color={item.color1} />
                                    </View>
                                    <Text style={styles.cardTitle}>{item.title}</Text>
                                    <Text style={styles.cardSubtitle}>{item.subtitle}</Text>
                                    <Text style={styles.cardDescription} numberOfLines={2}>
                                        {item.description}
                                    </Text>
                                    <View style={styles.arrowContainer}>
                                        <Ionicons name="arrow-forward-circle" size={32} color="rgba(255,255,255,0.9)" />
                                    </View>
                                </LinearGradient>
                            </TouchableOpacity>
                        ))}
                    </View>

                    {/* Daily Tip Section */}
                    <View style={styles.tipContainer}>
                        <LinearGradient
                            colors={['#0f172a', '#334155']}
                            style={styles.tipGradient}
                        >
                            <View style={styles.tipHeader}>
                                <Ionicons name="bulb" size={24} color="#facc15" />
                                <Text style={styles.tipTitle}>Daily Study Tip</Text>
                            </View>
                            <Text style={styles.tipText}>
                                "Spaced repetition is key! Review your notes 10 minutes after class, then 24 hours later, and finally a week later for maximum retention."
                            </Text>
                        </LinearGradient>
                    </View>

                    <View style={{ height: 40 }} />
                </View>
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
        // Removed fixed marginBottom to let the StatsCard define the space
        paddingBottom: 30,
    },
    headerBackground: {
        // DYNAMIC PADDING: Accounts for Status bar height on Android and Notch on iOS
        paddingTop: Platform.OS === 'android' ? StatusBar.currentHeight + 20 : 60,
        paddingHorizontal: 20,
        paddingBottom: 60, // Increased to make room for the absolute positioned stats card
        borderBottomLeftRadius: 32,
        borderBottomRightRadius: 32,
    },
    headerContent: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 20,
    },
    greeting: {
        fontSize: 26,
        fontWeight: '800',
        color: '#fff',
        letterSpacing: -0.5,
    },
    subGreeting: {
        fontSize: 15,
        color: 'rgba(255,255,255,0.7)',
        marginTop: 2,
    },
    profileButton: {
        width: 44,
        height: 44,
        borderRadius: 14,
        backgroundColor: 'rgba(255,255,255,0.15)',
        justifyContent: 'center',
        alignItems: 'center',
        borderWidth: 1,
        borderColor: 'rgba(255,255,255,0.2)',
    },
    statsCard: {
        flexDirection: 'row',
        backgroundColor: 'white',
        borderRadius: 20,
        padding: 18,
        position: 'absolute',
        bottom: -25, // Overlaps the bottom of the header
        left: 20,
        right: 20,
        elevation: 8,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.12,
        shadowRadius: 12,
        justifyContent: 'space-around',
        alignItems: 'center',
    },
    statItem: {
        alignItems: 'center',
        flex: 1,
    },
    statNumber: {
        fontSize: 18,
        fontWeight: '800',
        color: '#1e293b',
    },
    statLabel: {
        fontSize: 11,
        color: '#64748b',
        marginTop: 2,
        fontWeight: '600',
        textTransform: 'uppercase',
    },
    statDivider: {
        width: 1,
        height: 20,
        backgroundColor: '#f1f5f9',
    },
    content: {
        flex: 1,
    },
    scrollContent: {
        paddingTop: 30,
        paddingHorizontal: 20,
    },
    sectionTitle: {
        fontSize: 20,
        fontWeight: '800',
        color: '#1e293b',
        marginBottom: 16,
    },
    grid: {
        gap: 16,
        marginBottom: 24,
    },
    cardContainer: {
        borderRadius: 24,
        // Using shadow only on cardContainer for cleaner rendering
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 6 },
        shadowOpacity: 0.1,
        shadowRadius: 10,
    },
    cardGradient: {
        padding: 22,
        borderRadius: 24,
    },
    iconCircle: {
        width: 48,
        height: 48,
        borderRadius: 16,
        backgroundColor: 'white',
        justifyContent: 'center',
        alignItems: 'center',
        marginBottom: 16,
    },
    cardTitle: {
        fontSize: 22,
        fontWeight: '800',
        color: 'white',
        letterSpacing: -0.5,
    },
    cardSubtitle: {
        fontSize: 12,
        color: 'rgba(255,255,255,0.9)',
        marginBottom: 8,
        textTransform: 'uppercase',
        fontWeight: '700',
        letterSpacing: 1,
    },
    cardDescription: {
        fontSize: 14,
        color: 'rgba(255,255,255,0.85)',
        lineHeight: 20,
        maxWidth: '85%',
    },
    arrowContainer: {
        position: 'absolute',
        bottom: 22,
        right: 22,
    },
    tipContainer: {
        marginTop: 10,
        borderRadius: 24,
        overflow: 'hidden',
    },
    tipGradient: {
        padding: 24,
    },
    tipHeader: {
        flexDirection: 'row',
        alignItems: 'center',
        marginBottom: 12,
    },
    tipTitle: {
        fontSize: 18,
        fontWeight: '800',
        color: '#facc15',
        marginLeft: 10,
    },
    tipText: {
        color: '#f1f5f9',
        fontSize: 15,
        lineHeight: 24,
        fontStyle: 'italic',
        opacity: 0.9,
    },
});

export default AIScreen;