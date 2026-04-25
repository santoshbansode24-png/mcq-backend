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
    Platform,
    Alert
} from 'react-native';
import { useTheme } from '../context/ThemeContext';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';

const { width } = Dimensions.get('window');

const AIScreen = ({ navigation }) => {
    const { theme } = useTheme();

    const features = [
        {
            id: 'pdftoexam',
            title: 'Veeru Lens',
            subtitle: 'AI Doc Intelligence',
            description: 'Turn any PDF into a custom exam or flashcards.',
            icon: 'document-attach',
            color1: '#6366f1',
            color2: '#4f46e5',
            screen: 'PDFToExam',
            width: '100%',
            height: 120
        },
        {
            id: 'homework',
            title: 'Homework Helper',
            subtitle: 'Snap, Solve & Learn',
            description: 'Get instant step-by-step tutoring help.',
            icon: 'camera',
            color1: '#8b5cf6',
            color2: '#7c3aed',
            screen: 'HomeworkSolver',
            width: '100%',
            height: 120
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
                                style={[styles.tileWrapper, { width: item.width }]}
                                onPress={() => navigation.navigate(item.screen)}
                                activeOpacity={0.9}
                            >
                                {/* Gradient Card with Row Layout */}
                                <LinearGradient
                                    colors={[item.color1, item.color2]}
                                    start={{ x: 0, y: 0 }}
                                    end={{ x: 1, y: 1 }}
                                    style={[styles.rowTile, { height: item.height }]}
                                >
                                    {/* Glassy Glow Overlay */}
                                    <LinearGradient
                                        colors={['rgba(255,255,255,0.25)', 'rgba(255,255,255,0)']}
                                        style={styles.glossyOverlay}
                                    />

                                    {/* Icon Left */}
                                    <View style={styles.iconContainerRow}>
                                        <Ionicons name={item.icon} size={32} color={item.color1} />
                                    </View>

                                    {/* Text Middle */}
                                    <View style={styles.textContainerRow}>
                                        <Text style={styles.rowTitle}>{item.title}</Text>
                                        <Text style={styles.rowSubtitle}>{item.subtitle}</Text>
                                        <Text style={styles.rowDescription} numberOfLines={2}>{item.description}</Text>
                                    </View>

                                    {/* Arrow Right */}
                                    <View style={styles.arrowContainerRow}>
                                        <Ionicons name="chevron-forward" size={24} color="#fff" />
                                    </View>
                                </LinearGradient>
                            </TouchableOpacity>
                        ))}
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
        fontFamily: 'NotoSans-Bold',
    },
    subGreeting: {
        fontSize: 15,
        color: 'rgba(255,255,255,0.7)',
        marginTop: 2,
        fontFamily: 'NotoSans-Regular',
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
        fontFamily: 'NotoSans-Bold',
    },
    statLabel: {
        fontSize: 11,
        color: '#64748b',
        marginTop: 2,
        fontWeight: '600',
        textTransform: 'uppercase',
        fontFamily: 'NotoSans-Bold',
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
        fontFamily: 'NotoSans-Bold',
    },
    grid: {
        flexDirection: 'column', // Stack vertically
        gap: 16, // Vertical gap
        marginBottom: 24,
    },
    tileWrapper: {
        marginBottom: 0,
        borderRadius: 20,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.1,
        shadowRadius: 8,
        elevation: 4,
    },
    rowTile: {
        width: '100%',
        borderRadius: 24,
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 20,
        justifyContent: 'space-between',
        overflow: 'hidden',
        borderWidth: 1,
        borderColor: 'rgba(255,255,255,0.2)',
    },
    glossyOverlay: {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        height: '50%',
    },
    iconContainerRow: {
        width: 60,
        height: 60,
        borderRadius: 20,
        backgroundColor: '#fff',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 16,
        elevation: 4,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.1,
        shadowRadius: 4,
    },
    textContainerRow: {
        flex: 1,
        justifyContent: 'center',
    },
    rowTitle: {
        fontSize: 19,
        fontWeight: '900',
        color: 'white',
        marginBottom: 2,
        fontFamily: 'NotoSans-Bold',
    },
    rowSubtitle: {
        fontSize: 10,
        color: 'rgba(255,255,255,0.85)',
        textTransform: 'uppercase',
        fontWeight: 'bold',
        letterSpacing: 1,
        fontFamily: 'NotoSans-Bold',
        marginBottom: 4,
    },
    rowDescription: {
        fontSize: 12,
        color: 'rgba(255,255,255,0.9)',
        fontFamily: 'NotoSans-Regular',
        lineHeight: 16,
    },
    arrowContainerRow: {
        padding: 8,
        backgroundColor: 'rgba(255,255,255,0.2)',
        borderRadius: 14,
        borderWidth: 1,
        borderColor: 'rgba(255,255,255,0.3)',
    },
});

export default AIScreen;