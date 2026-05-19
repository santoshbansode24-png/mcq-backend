import React, { useState, useEffect, useRef, useCallback } from 'react';
import {
    View,
    Text,
    StyleSheet,
    TextInput,
    TouchableOpacity,
    FlatList,
    KeyboardAvoidingView,
    Platform,
    ActivityIndicator,
    Animated,
    Dimensions,
    SafeAreaView,
    StatusBar,
    Alert
} from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import YoutubePlayer from 'react-native-youtube-iframe';
import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { API_URL, BASE_URL } from '../api/config';
import { useTheme } from '../context/ThemeContext';


const { width: SCREEN_WIDTH, height: SCREEN_HEIGHT } = Dimensions.get('window');

// Individual Floating Emoji Component
const FloatingEmoji = React.memo(({ id, type, onComplete }) => {
    const animation = useRef(new Animated.Value(0)).current;
    
    // Choose emoji symbol
    const emojiMap = {
        like: '👍',
        love: '❤️',
        clap: '👏',
        idea: '💡',
        wow: '😮'
    };
    const emoji = emojiMap[type] || '👍';

    // Randomize initial horizontal offset and wobble magnitude
    const randomX = useRef(Math.random() * 40 - 20).current;
    const wobbleRange = useRef(Math.random() * 30 + 15).current;

    useEffect(() => {
        Animated.timing(animation, {
            toValue: 1,
            duration: 2500,
            useNativeDriver: true
        }).start(() => {
            onComplete(id);
        });
    }, []);

    // Interpolate Y (floating upward)
    const translateY = animation.interpolate({
        inputRange: [0, 1],
        outputRange: [0, -320]
    });

    // Interpolate X (wobble left/right)
    const translateX = animation.interpolate({
        inputRange: [0, 0.25, 0.5, 0.75, 1],
        outputRange: [randomX, randomX + wobbleRange, randomX - wobbleRange, randomX + wobbleRange / 2, randomX]
    });

    // Interpolate Opacity (fade out at the end)
    const opacity = animation.interpolate({
        inputRange: [0, 0.1, 0.8, 1],
        outputRange: [0, 1, 1, 0]
    });

    // Interpolate Scale (pop in, then shrink slightly)
    const scale = animation.interpolate({
        inputRange: [0, 0.1, 0.9, 1],
        outputRange: [0.4, 1.2, 1.0, 0.5]
    });

    return (
        <Animated.View
            style={[
                styles.emojiWrapper,
                {
                    transform: [
                        { translateY },
                        { translateX },
                        { scale }
                    ],
                    opacity
                }
            ]}
        >
            <Text style={styles.emojiText}>{emoji}</Text>
        </Animated.View>
    );
});

export default function LiveClassScreen({ route, navigation }) {
    const { classUpdate } = route.params || {};
    const { theme, isDarkMode } = useTheme();

    const [playing, setPlaying] = useState(true);
    const [messages, setMessages] = useState([]);
    const [inputText, setInputText] = useState('');
    const [viewerCount, setViewerCount] = useState(0);
    const [floatingReactions, setFloatingReactions] = useState([]);
    const [sendingMsg, setSendingMsg] = useState(false);
    const [studentId, setStudentId] = useState(route.params?.userId || null);
    const [playerError, setPlayerError] = useState(false);


    // Track polling state & IDs
    const lastChatId = useRef(0);
    const lastReactionId = useRef(0);
    const pollingTimer = useRef(null);
    const flatListRef = useRef(null);
    const isPollingRef = useRef(false);

    // Extract youtube video ID from payload
    const youtubeId = classUpdate?.payload?.youtube_id || '';


    useEffect(() => {
        // 1. Fetch real student user ID from storage if not provided in params
        const getUserId = async () => {
            let activeId = route.params?.userId;
            if (!activeId) {
                try {
                    const savedUser = await AsyncStorage.getItem('user_data');
                    if (savedUser) {
                        const parsed = JSON.parse(savedUser);
                        activeId = parsed.user_id || parsed.id;
                    }
                } catch (e) {
                    console.log('Failed to fetch user_data from AsyncStorage:', e);
                }
            }
            if (activeId) {
                setStudentId(activeId);
                logAttendance(activeId);
            } else {
                // Fallback to teacher_id to prevent screen crash
                setStudentId(classUpdate.teacher_id);
                logAttendance(classUpdate.teacher_id);
            }
        };

        getUserId();

        // 2. Fetch initial chat & reactions, then start polling
        pollActivity();
        pollingTimer.current = setInterval(pollActivity, 3000);

        return () => {
            if (pollingTimer.current) {
                clearInterval(pollingTimer.current);
            }
        };
    }, []);

    const logAttendance = async (id) => {
        try {
            await axios.post(`${API_URL}/student/join_live_class.php`, {
                student_id: id, 
                class_update_id: classUpdate.notification_id
            });
        } catch (e) {
            console.log('[AttendanceLog] Error:', e);
        }
    };

    const pollActivity = async () => {
        if (isPollingRef.current) return;
        isPollingRef.current = true;
        try {
            const response = await axios.get(
                `${API_URL}/student/get_live_class_activity.php?class_update_id=${classUpdate.notification_id}&last_chat_id=${lastChatId.current}&last_reaction_id=${lastReactionId.current}`
            );

            if (response.data.status === 'success') {
                const { new_chats, new_reactions, viewer_count } = response.data.data;

                // Update viewer count
                setViewerCount(viewer_count);

                // Add new chats to messages
                if (new_chats && new_chats.length > 0) {
                    setMessages(prev => {
                        const merged = [...prev, ...new_chats];
                        // Deduplicate by ID
                        const unique = [];
                        const map = new Map();
                        for (const item of merged) {
                            if (!map.has(item.id)) {
                                map.set(item.id, true);
                                unique.push(item);
                            }
                        }
                        return unique;
                    });
                    
                    // Update last chat ID
                    const maxChatId = Math.max(...new_chats.map(c => c.id));
                    if (maxChatId > lastChatId.current) {
                        lastChatId.current = maxChatId;
                    }

                    // Auto-scroll to bottom
                    setTimeout(() => {
                        flatListRef.current?.scrollToEnd({ animated: true });
                    }, 300);
                }

                // Add floating reactions
                if (new_reactions && new_reactions.length > 0) {
                    new_reactions.forEach(react => {
                        triggerFloatingReaction(react.reaction_type);
                    });

                    // Update last reaction ID
                    const maxReactId = Math.max(...new_reactions.map(r => r.id));
                    if (maxReactId > lastReactionId.current) {
                        lastReactionId.current = maxReactId;
                    }
                }
            }
        } catch (e) {
            console.log('[PollingActivity] Error:', e);
        } finally {
            isPollingRef.current = false;
        }
    };

    const handleSendMessage = async () => {
        if (!inputText.trim()) return;

        setInputText('');
        setSendingMsg(true);

        try {
            const res = await axios.post(`${API_URL}/student/send_live_chat.php`, {
                student_id: studentId,
                class_update_id: classUpdate.notification_id,
                message: inputText.trim()
            });

            if (res.data.status === 'success') {
                const newMsg = res.data.data;
                setMessages(prev => [...prev, newMsg]);
                if (newMsg.id > lastChatId.current) {
                    lastChatId.current = newMsg.id;
                }
                setTimeout(() => {
                    flatListRef.current?.scrollToEnd({ animated: true });
                }, 100);
            }
        } catch (e) {
            Alert.alert('Error', 'Failed to send comment. Please try again.');
        } finally {
            setSendingMsg(false);
        }
    };

    const sendEmojiReaction = async (type) => {
        // Trigger locally instantly
        triggerFloatingReaction(type);

        // Send to backend
        try {
            await axios.post(`${API_URL}/student/send_reaction.php`, {
                class_update_id: classUpdate.notification_id,
                reaction_type: type
            });
        } catch (e) {
            console.log('[ReactionError]:', e);
        }
    };

    const triggerFloatingReaction = (type) => {
        const id = Math.random().toString();
        setFloatingReactions(prev => [...prev, { id, type }]);
    };

    const removeFloatingEmoji = useCallback((id) => {
        setFloatingReactions(prev => prev.filter(emoji => emoji.id !== id));
    }, []);

    const renderChatMessage = ({ item }) => {
        const isSelf = item.student_id === studentId;
        return (
            <View style={styles.chatRow}>
                <Text style={styles.chatSender}>{item.student_name}: </Text>
                <Text style={[styles.chatText, { color: isDarkMode ? '#e2e8f0' : '#334155' }]}>
                    {item.message}
                </Text>
            </View>
        );
    };


    return (
        <SafeAreaView style={[styles.container, { backgroundColor: isDarkMode ? '#0f172a' : '#f8fafc' }]}>
            <StatusBar barStyle="light-content" backgroundColor="#000000" />
            
            {/* Header */}
            <View style={styles.header}>
                <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backBtn}>
                    <MaterialCommunityIcons name="chevron-left" size={28} color="#ffffff" />
                </TouchableOpacity>
                <View style={styles.headerTitleContainer}>
                    <Text style={styles.headerTitle} numberOfLines={1}>
                        {classUpdate.title}
                    </Text>
                    <Text style={styles.headerSub}>
                        Teacher: {classUpdate.teacher_name}
                    </Text>
                </View>
            </View>

            {/* Video Player */}
            <View style={styles.playerWrapper}>
                {youtubeId && !playerError ? (
                    <YoutubePlayer
                        height={SCREEN_WIDTH * (9 / 16)}
                        play={playing}
                        videoId={youtubeId}
                        onError={(err) => {
                            console.log('Player error:', err);
                            setPlayerError(true);
                        }}
                        initialPlayerParams={{
                            controls: 1,
                            rel: 0,
                            playsinline: 1,
                            modestbranding: 1
                        }}
                    />
                ) : (
                    <View style={styles.errorVideo}>
                        <MaterialCommunityIcons name="video-off" size={48} color="#f43f5e" />
                        <Text style={[styles.errorVideoText, { color: '#f43f5e', fontWeight: 'bold' }]}>
                            {playerError ? 'Live Stream Unavailable' : 'Invalid Video Link'}
                        </Text>
                        <Text style={{ color: '#94a3b8', fontSize: 12, marginTop: 4, textAlign: 'center', paddingHorizontal: 20 }}>
                            {playerError ? 'This stream might be private, deleted, or offline. Check with your teacher.' : 'The video URL configured by the teacher is incorrect.'}
                        </Text>
                    </View>
                )}
            </View>

            <KeyboardAvoidingView
                behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
                style={{ flex: 1 }}
                keyboardVerticalOffset={Platform.OS === 'ios' ? 0 : 0}
            >
                {/* Live Stats Bar */}
                <View style={styles.statsBar}>
                    <View style={styles.liveBadgeContainer}>
                        <View style={styles.pulseDot} />
                        <Text style={styles.liveText}>LIVE</Text>
                    </View>
                    <View style={styles.viewerBadge}>
                        <MaterialCommunityIcons name="eye" size={16} color="#64748b" />
                        <Text style={styles.viewerCountText}>{viewerCount} watching</Text>
                    </View>
                </View>

                {/* Comments List */}
                <View style={styles.chatSection}>
                    <Text style={[styles.chatTitle, { color: isDarkMode ? '#f8fafc' : '#1e293b' }]}>
                        Live Class Chat
                    </Text>
                    
                    <FlatList
                        ref={flatListRef}
                        data={messages}
                        renderItem={renderChatMessage}
                        keyExtractor={item => item.id.toString()}
                        contentContainerStyle={styles.chatList}
                        showsVerticalScrollIndicator={false}
                        onContentSizeChange={() => flatListRef.current?.scrollToEnd({ animated: true })}
                    />
                </View>

                {/* Floating Reactions overlay */}
                <View style={styles.reactionsCanvas} pointerEvents="none">
                    {floatingReactions.map(reaction => (
                        <FloatingEmoji
                            key={reaction.id}
                            id={reaction.id}
                            type={reaction.type}
                            onComplete={removeFloatingEmoji}
                        />
                    ))}
                </View>

                {/* Horizontal Quick Reactions */}
                <View style={styles.reactionTriggerBar}>
                    <TouchableOpacity style={styles.reactionBtn} onPress={() => sendEmojiReaction('love')}>
                        <Text style={styles.reactionBtnText}>❤️</Text>
                    </TouchableOpacity>
                    <TouchableOpacity style={styles.reactionBtn} onPress={() => sendEmojiReaction('like')}>
                        <Text style={styles.reactionBtnText}>👍</Text>
                    </TouchableOpacity>
                    <TouchableOpacity style={styles.reactionBtn} onPress={() => sendEmojiReaction('clap')}>
                        <Text style={styles.reactionBtnText}>👏</Text>
                    </TouchableOpacity>
                    <TouchableOpacity style={styles.reactionBtn} onPress={() => sendEmojiReaction('idea')}>
                        <Text style={styles.reactionBtnText}>💡</Text>
                    </TouchableOpacity>
                    <TouchableOpacity style={styles.reactionBtn} onPress={() => sendEmojiReaction('wow')}>
                        <Text style={styles.reactionBtnText}>😮</Text>
                    </TouchableOpacity>
                </View>

                {/* TextInput Send row */}
                <View style={[styles.inputRow, { backgroundColor: isDarkMode ? '#1e293b' : '#ffffff' }]}>
                    <TextInput
                        style={[styles.textInput, { color: isDarkMode ? '#ffffff' : '#000000' }]}
                        placeholder="Say something nice..."
                        placeholderTextColor="#94a3b8"
                        value={inputText}
                        onChangeText={setInputText}
                        onSubmitEditing={handleSendMessage}
                    />
                    <TouchableOpacity 
                        style={[styles.sendBtn, { backgroundColor: theme.primary }]}
                        onPress={handleSendMessage}
                        disabled={sendingMsg}
                    >
                        {sendingMsg ? (
                            <ActivityIndicator size="small" color="#ffffff" />
                        ) : (
                            <MaterialCommunityIcons name="send" size={20} color="#ffffff" />
                        )}
                    </TouchableOpacity>
                </View>
            </KeyboardAvoidingView>
        </SafeAreaView>
    );
}

const styles = StyleSheet.create({
    container: {
        flex: 1,
    },
    header: {
        height: 60,
        backgroundColor: '#000000',
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 12,
    },
    backBtn: {
        padding: 4,
    },
    headerTitleContainer: {
        flex: 1,
        marginLeft: 8,
    },
    headerTitle: {
        color: '#ffffff',
        fontSize: 16,
        fontWeight: 'bold',
    },
    headerSub: {
        color: '#94a3b8',
        fontSize: 12,
    },
    playerWrapper: {
        width: '100%',
        aspectRatio: 16 / 9,
        backgroundColor: '#000000',
    },
    errorVideo: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
    },
    errorVideoText: {
        color: '#94a3b8',
        marginTop: 8,
        fontSize: 14,
    },
    statsBar: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 16,
        paddingVertical: 10,
        borderBottomWidth: 1,
        borderBottomColor: 'rgba(0,0,0,0.05)',
        gap: 12,
    },
    liveBadgeContainer: {
        flexDirection: 'row',
        alignItems: 'center',
        backgroundColor: '#FFE4E6',
        paddingHorizontal: 8,
        paddingVertical: 4,
        borderRadius: 6,
    },
    pulseDot: {
        width: 8,
        height: 8,
        borderRadius: 4,
        backgroundColor: '#E11D48',
        marginRight: 6,
    },
    liveText: {
        color: '#E11D48',
        fontSize: 11,
        fontWeight: '900',
    },
    viewerBadge: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 4,
    },
    viewerCountText: {
        color: '#64748b',
        fontSize: 12,
        fontWeight: '600',
    },
    chatSection: {
        flex: 1,
        paddingTop: 12,
    },
    chatTitle: {
        fontSize: 15,
        fontWeight: 'bold',
        paddingHorizontal: 16,
        marginBottom: 8,
    },
    chatList: {
        paddingHorizontal: 16,
        paddingBottom: 20,
    },
    chatRow: {
        flexDirection: 'row',
        marginBottom: 10,
        alignItems: 'flex-start',
    },
    chatSender: {
        fontWeight: 'bold',
        color: '#E11D48',
        fontSize: 13,
    },
    chatText: {
        fontSize: 13,
        flex: 1,
    },
    reactionsCanvas: {
        position: 'absolute',
        bottom: 120,
        right: 20,
        width: 100,
        height: 350,
        zIndex: 999,
        alignItems: 'center',
        justifyContent: 'flex-end',
    },
    emojiWrapper: {
        position: 'absolute',
        bottom: 0,
    },
    emojiText: {
        fontSize: 28,
    },
    reactionTriggerBar: {
        flexDirection: 'row',
        justifyContent: 'space-around',
        paddingVertical: 8,
        borderTopWidth: 1,
        borderTopColor: 'rgba(0,0,0,0.05)',
        backgroundColor: 'rgba(0,0,0,0.02)',
    },
    reactionBtn: {
        width: 44,
        height: 44,
        borderRadius: 22,
        backgroundColor: '#ffffff',
        justifyContent: 'center',
        alignItems: 'center',
        elevation: 3,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.1,
        shadowRadius: 3,
    },
    reactionBtnText: {
        fontSize: 20,
    },
    inputRow: {
        flexDirection: 'row',
        padding: 12,
        alignItems: 'center',
        borderTopWidth: 1,
        borderTopColor: 'rgba(0,0,0,0.05)',
    },
    textInput: {
        flex: 1,
        height: 44,
        borderRadius: 22,
        backgroundColor: 'rgba(148,163,184,0.1)',
        paddingHorizontal: 16,
        marginRight: 10,
        fontSize: 14,
    },
    sendBtn: {
        width: 44,
        height: 44,
        borderRadius: 22,
        justifyContent: 'center',
        alignItems: 'center',
    },
});
