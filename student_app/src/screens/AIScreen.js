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
            title: 'PDF-to-Exam',
            subtitle: 'Doc Analyzer',
            description: 'Turn any PDF into a custom exam.',
            icon: 'document-attach',
            // Indigo to Deep Blue
            color1: '#4f46e5',
            color2: '#2563eb',
            screen: 'PDFToExam',
            width: '100%',
            height: 110
        },
        {
            id: 'homework',
            title: 'Homework Helper',
            subtitle: 'Snap & Solve',
            description: 'Get instant step-by-step help.',
            icon: 'camera',
            // Vibrant Purple to Pink
            color1: '#7c3aed',
            color2: '#d946ef',
            screen: 'HomeworkSolver',
            width: '100%',
            height: 110
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
                                onPress={() => {
                                    if (item.id === 'homework') {
                                        Alert.alert('Coming Soon!', 'The Homework Solver is currently in development and will be available shortly.');
                                    } else {
                                        navigation.navigate(item.screen);
                                    }
                                }}
                                activeOpacity={0.9}
                            >
                                {/* Gradient Card with Row Layout */}
                                <LinearGradient
                                    colors={[item.color1, item.color2]}
                                    start={{ x: 0, y: 0 }}
                                    end={{ x: 1, y: 1 }}
                                    style={[styles.rowTile, { height: item.height }]}
                                >
                                    {/* Icon Left */}
                                    <View style={styles.iconContainerRow}>
                                        <Ionicons name={item.icon} size={32} color={item.color1} />
                                    </View>

                                    {/* Text Middle */}
                                    <View style={styles.textContainerRow}>
                                        <Text style={styles.rowTitle}>{item.title}</Text>
                                        <Text style={styles.rowSubtitle}>{item.subtitle}</Text>
                                    </View>

                                    {/* Arrow Right */}
                                    <View style={styles.arrowContainerRow}>
                                        <Ionicons name="chevron-forward" size={24} color="rgba(255,255,255,0.9)" />
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
        borderRadius: 20,
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 20,
        justifyContent: 'space-between', // Spread items
    },
    iconContainerRow: {
        width: 56,
        height: 56,
        borderRadius: 18,
        backgroundColor: 'rgba(255,255,255,0.9)',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 16,
    },
    textContainerRow: {
        flex: 1,
        justifyContent: 'center',
    },
    rowTitle: {
        fontSize: 18,
        fontWeight: 'bold',
        color: 'white',
        marginBottom: 4,
        fontFamily: 'NotoSans-Bold',
    },
    rowSubtitle: {
        fontSize: 12,
        color: 'rgba(255,255,255,0.9)',
        textTransform: 'uppercase',
        fontWeight: '600',
        letterSpacing: 0.5,
        fontFamily: 'NotoSans-Bold',
    },
    arrowContainerRow: {
        padding: 8,
        backgroundColor: 'rgba(255,255,255,0.2)',
        borderRadius: 12,
    },
});

export default AIScreen;