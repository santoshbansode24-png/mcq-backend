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
            description: 'Turn any PDF into a custom exam or smart flashcards instantly.',
            icon: 'document-attach',
            color1: '#f43f5e', // Rose
            color2: '#fb923c', // Orange
            iconColor: '#f43f5e',
            screen: 'PDFToExam',
            height: 140
        },
        {
            id: 'homework',
            title: 'Homework Helper',
            subtitle: 'Snap, Solve & Learn',
            description: 'Get step-by-step expert tutoring help for any problem.',
            icon: 'camera',
            color1: '#0ea5e9', // Sky Blue
            color2: '#10b981', // Emerald
            iconColor: '#0ea5e9',
            screen: 'HomeworkSolver',
            height: 140
        }
    ];

    return (
        <View style={styles.container}>
            <StatusBar barStyle="light-content" backgroundColor="transparent" translucent />

            <ScrollView style={styles.content} showsVerticalScrollIndicator={false} bounces={false}>
                {/* Premium Header */}
                <View style={styles.headerContainer}>
                    <LinearGradient colors={['#0f172a', '#1e293b']} style={styles.headerBackground}>
                        {/* Decorative Background Elements */}
                        <View style={[styles.headerDeco, { top: -20, right: -20, width: 150, height: 150, borderRadius: 75, backgroundColor: 'rgba(99,102,241,0.15)' }]} />
                        <View style={[styles.headerDeco, { top: 60, left: -30, width: 100, height: 100, borderRadius: 50, backgroundColor: 'rgba(236,72,153,0.1)' }]} />

                        <View style={styles.headerContent}>
                            <View>
                                <Text style={styles.greeting}>AI Learning Hub</Text>
                                <Text style={styles.subGreeting}>Supercharge your studies 🚀</Text>
                            </View>
                            <TouchableOpacity style={styles.profileButton}>
                                <Ionicons name="sparkles" size={24} color="#fbbf24" />
                            </TouchableOpacity>
                        </View>

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
                    <Text style={styles.sectionTitle}>Smart Tools</Text>

                    <View style={styles.grid}>
                        {features.map((item) => (
                            <TouchableOpacity
                                key={item.id}
                                style={styles.tileWrapper}
                                onPress={() => navigation.navigate(item.screen)}
                                activeOpacity={0.9}
                            >
                                <LinearGradient
                                    colors={[item.color1, item.color2]}
                                    start={{ x: 0, y: 0 }}
                                    end={{ x: 1, y: 1 }}
                                    style={[styles.rowTile, { height: item.height }]}
                                >
                                    {/* Glassy Overlay for Shine */}
                                    <LinearGradient
                                        colors={['rgba(255,255,255,0.3)', 'rgba(255,255,255,0)']}
                                        style={styles.glossyOverlay}
                                    />

                                    {/* Floating Geometric Shapes for 3D Effect */}
                                    <View style={[styles.shape, { top: -20, right: -10, width: 80, height: 80, borderRadius: 40, backgroundColor: 'rgba(255,255,255,0.1)' }]} />
                                    <View style={[styles.shape, { bottom: -30, right: 40, width: 100, height: 100, borderRadius: 50, backgroundColor: 'rgba(255,255,255,0.05)' }]} />

                                    {/* Left Content Area */}
                                    <View style={styles.contentLeft}>
                                        <View style={styles.iconBadge}>
                                            <Ionicons name={item.icon} size={28} color={item.iconColor} />
                                        </View>
                                        <View style={styles.textStack}>
                                            <Text style={styles.rowSubtitle}>{item.subtitle}</Text>
                                            <Text style={styles.rowTitle}>{item.title}</Text>
                                            <Text style={styles.rowDescription} numberOfLines={2}>{item.description}</Text>
                                        </View>
                                    </View>

                                    {/* Right Arrow Button */}
                                    <View style={styles.arrowBadge}>
                                        <Ionicons name="arrow-forward" size={24} color={item.color1} />
                                    </View>
                                </LinearGradient>
                            </TouchableOpacity>
                        ))}
                    </View>

                    <View style={{ height: 60 }} />
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
        paddingBottom: 35,
    },
    headerBackground: {
        paddingTop: Platform.OS === 'android' ? StatusBar.currentHeight + 20 : 60,
        paddingHorizontal: 24,
        paddingBottom: 65,
        borderBottomLeftRadius: 40,
        borderBottomRightRadius: 40,
        position: 'relative',
        overflow: 'visible',
    },
    headerDeco: {
        position: 'absolute',
    },
    headerContent: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: 25,
        zIndex: 2,
    },
    greeting: {
        fontSize: 28,
        fontWeight: '900',
        color: '#fff',
        letterSpacing: -0.5,
        fontFamily: 'NotoSans-Bold',
    },
    subGreeting: {
        fontSize: 16,
        color: '#cbd5e1',
        marginTop: 4,
        fontFamily: 'NotoSans-Regular',
    },
    profileButton: {
        width: 48,
        height: 48,
        borderRadius: 16,
        backgroundColor: 'rgba(255,255,255,0.1)',
        justifyContent: 'center',
        alignItems: 'center',
        borderWidth: 1,
        borderColor: 'rgba(255,255,255,0.15)',
        backdropFilter: 'blur(10px)',
    },
    statsCard: {
        flexDirection: 'row',
        backgroundColor: '#ffffff',
        borderRadius: 24,
        paddingVertical: 20,
        position: 'absolute',
        bottom: -30,
        left: 24,
        right: 24,
        elevation: 12,
        shadowColor: '#0f172a',
        shadowOffset: { width: 0, height: 8 },
        shadowOpacity: 0.15,
        shadowRadius: 16,
        justifyContent: 'space-around',
        alignItems: 'center',
        zIndex: 10,
    },
    statItem: {
        alignItems: 'center',
        flex: 1,
    },
    statNumber: {
        fontSize: 20,
        fontWeight: '900',
        color: '#0f172a',
        fontFamily: 'NotoSans-Bold',
    },
    statLabel: {
        fontSize: 12,
        color: '#64748b',
        marginTop: 4,
        fontWeight: '700',
        textTransform: 'uppercase',
        letterSpacing: 0.5,
        fontFamily: 'NotoSans-Bold',
    },
    statDivider: {
        width: 1,
        height: 24,
        backgroundColor: '#e2e8f0',
    },
    content: {
        flex: 1,
    },
    scrollContent: {
        paddingTop: 45,
        paddingHorizontal: 20,
    },
    sectionTitle: {
        fontSize: 22,
        fontWeight: '900',
        color: '#0f172a',
        marginBottom: 20,
        fontFamily: 'NotoSans-Bold',
        letterSpacing: -0.5,
    },
    grid: {
        flexDirection: 'column',
        gap: 20,
    },
    tileWrapper: {
        borderRadius: 28,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 8 },
        shadowOpacity: 0.2,
        shadowRadius: 12,
        elevation: 8,
    },
    rowTile: {
        width: '100%',
        borderRadius: 28,
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 24,
        justifyContent: 'space-between',
        position: 'relative',
        overflow: 'hidden',
    },
    glossyOverlay: {
        position: 'absolute',
        top: 0,
        left: 0,
        right: 0,
        height: '45%',
    },
    shape: {
        position: 'absolute',
    },
    contentLeft: {
        flex: 1,
        flexDirection: 'row',
        alignItems: 'center',
        zIndex: 2,
    },
    iconBadge: {
        width: 56,
        height: 56,
        borderRadius: 18,
        backgroundColor: '#fff',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 16,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.1,
        shadowRadius: 6,
        elevation: 5,
    },
    textStack: {
        flex: 1,
        paddingRight: 10,
    },
    rowSubtitle: {
        fontSize: 11,
        color: 'rgba(255,255,255,0.9)',
        textTransform: 'uppercase',
        fontWeight: '900',
        letterSpacing: 1.2,
        fontFamily: 'NotoSans-Bold',
        marginBottom: 4,
    },
    rowTitle: {
        fontSize: 22,
        fontWeight: '900',
        color: '#ffffff',
        marginBottom: 4,
        fontFamily: 'NotoSans-Bold',
        letterSpacing: -0.5,
    },
    rowDescription: {
        fontSize: 13,
        color: 'rgba(255,255,255,0.85)',
        fontFamily: 'NotoSans-Regular',
        lineHeight: 18,
    },
    arrowBadge: {
        width: 44,
        height: 44,
        backgroundColor: '#fff',
        borderRadius: 22,
        justifyContent: 'center',
        alignItems: 'center',
        zIndex: 2,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.15,
        shadowRadius: 8,
        elevation: 6,
    },
});

export default AIScreen;