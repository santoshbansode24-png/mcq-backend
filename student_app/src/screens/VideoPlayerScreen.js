import React, { useState, useCallback, useMemo } from 'react';
import { View, StyleSheet, TouchableOpacity, Text, StatusBar, Dimensions, BackHandler, ActivityIndicator, Image } from 'react-native';
import YoutubePlayer from 'react-native-youtube-iframe';
import { Video, ResizeMode } from 'expo-av';
import * as ScreenOrientation from 'expo-screen-orientation';
import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { BASE_URL, API_URL } from '../api/config';
import { LinearGradient } from 'expo-linear-gradient';

const { width, height } = Dimensions.get('window');

const VideoPlayerScreen = ({ route, navigation }) => {
    const { videoUrl, title, activeTask } = route.params || {};
    const [playing, setPlaying] = useState(true);
    const [isFullScreen, setIsFullScreen] = useState(false);
    const [isReady, setIsReady] = useState(false); // Track if video is ready to play

    const onStateChange = useCallback((state) => {
        if (state === "ended") {
            setPlaying(false);
        }
        if (state === "playing") {
            setIsReady(true);
        }
        if (state === "buffering") {
            // Optional: You could show a smaller spinner here, 
            // but for now we'll let the native YouTube spinner handle it to avoid flickering
        }
    }, []);

    const onReadyHandler = useCallback(() => {
        setIsReady(true);
    }, []);

    // Full screen toggle handler
    const onFullScreenChange = useCallback((isFullScreen) => {
        setIsFullScreen(isFullScreen);
        if (isFullScreen) {
            ScreenOrientation.lockAsync(ScreenOrientation.OrientationLock.LANDSCAPE);
        } else {
            ScreenOrientation.lockAsync(ScreenOrientation.OrientationLock.PORTRAIT);
        }
    }, []);

    // Timer State and Logic
    const [taskTimer, setTaskTimer] = useState(0);
    const [isTaskActive, setIsTaskActive] = useState(false);

    // Initial Timer Setup
    React.useEffect(() => {
        if (activeTask && !isTaskActive) {
            setTaskTimer(activeTask.duration_minutes * 60);
            setIsTaskActive(true);
        }

        // Handle physical back button and ensure orientation reset on unmount
        const backAction = () => {
            if (isFullScreen) {
                ScreenOrientation.lockAsync(ScreenOrientation.OrientationLock.PORTRAIT);
                setIsFullScreen(false);
                return true;
            }
            navigation.goBack();
            return true; // Prevent default behavior
        };

        const backHandler = BackHandler.addEventListener(
            "hardwareBackPress",
            backAction
        );

        return () => {
            backHandler.remove();
            // Force reset to Portrait when leaving VideoPlayer
            ScreenOrientation.lockAsync(ScreenOrientation.OrientationLock.PORTRAIT);
        };
    }, [activeTask, navigation, isFullScreen]);

    // Countdown Logic
    React.useEffect(() => {
        let interval = null;
        if (isTaskActive && taskTimer > 0) {
            interval = setInterval(() => {
                setTaskTimer((prev) => prev - 1);
            }, 1000);
        } else if (taskTimer === 0 && isTaskActive) {
            finishTask();
        }
        return () => clearInterval(interval);
    }, [isTaskActive, taskTimer]);

    const finishTask = async () => {
        setIsTaskActive(false);
        try {
            // Get User ID
            const userDataStr = await AsyncStorage.getItem('user_data');
            const userData = userDataStr ? JSON.parse(userDataStr) : null;
            const userId = userData?.user_id || userData?.id;

            if (userId && activeTask) {
                await axios.post(`${API_URL}/update_task_status.php`, {
                    user_id: userId,
                    task_id: activeTask.task_id,
                    status: 'completed'
                });
                alert(`Mission Complete! You earned ${activeTask.xp_reward} XP!`);
            }
        } catch (e) {
            console.error('[VideoPlayer] Error finishing task:', e);
        }
    };

    const formatTimer = (seconds) => {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return `${m}:${s < 10 ? '0' : ''}${s}`;
    };

    // Robust Video ID Extractor
    const getVideoId = (url) => {
        if (!url) return null;
        const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=)([^#&?]*).*/;
        const match = url.match(regExp);
        if (match && match[2]) {
            const cleanId = match[2].replace(/[^a-zA-Z0-9_-]/g, '');
            if (cleanId.length >= 11) {
                return cleanId.substring(0, 11);
            }
        }
        return null;
    };

    const videoId = useMemo(() => getVideoId(videoUrl), [videoUrl]);
    const thumbnailUrl = useMemo(() => videoId ? `https://img.youtube.com/vi/${videoId}/maxresdefault.jpg` : null, [videoId]);

    // Construct full URL for non-YouTube videos
    const getFullVideoUrl = (url) => {
        if (!url) return null;
        if (url.startsWith('http')) return url;
        const cleanPath = url.startsWith('/') ? url.substring(1) : url;
        return `${BASE_URL}/${cleanPath}`;
    };

    const fullVideoUrl = getFullVideoUrl(videoUrl);

    return (
        <View style={styles.container}>
            <StatusBar hidden={isFullScreen} barStyle="light-content" backgroundColor="black" translucent />

            {/* Header - Only visible in Portrait */}
            {!isFullScreen && (
                <View style={styles.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={styles.closeButton}>
                        <Text style={styles.closeText}>✕ Close</Text>
                    </TouchableOpacity>
                    <Text style={styles.title} numberOfLines={1}>{title || 'Video Player'}</Text>
                </View>
            )}

            {/* Timer Overlay */}
            {isTaskActive && (
                <View style={[styles.timerOverlay, isFullScreen ? styles.timerOverlayFullScreen : {}]}>
                    <Text style={styles.timerText}>⏳ {formatTimer(taskTimer)}</Text>
                    <TouchableOpacity onPress={finishTask} style={styles.finishBtn}>
                        <Text style={styles.finishBtnText}>Done</Text>
                    </TouchableOpacity>
                </View>
            )}

            <View style={[styles.videoWrapper, isFullScreen && styles.fullScreenWrapper]}>

                {/* Loading State Overlay */}
                {!isReady && (
                    <View style={styles.loadingOverlay}>
                        {thumbnailUrl ? (
                            <Image
                                source={{ uri: thumbnailUrl }}
                                style={[StyleSheet.absoluteFill, { opacity: 0.4 }]}
                                resizeMode="cover"
                            />
                        ) : (
                            <View style={[StyleSheet.absoluteFill, { backgroundColor: '#1a1a1a' }]} />
                        )}
                        <View style={styles.loadingInfo}>
                            <ActivityIndicator size="large" color="#ffffff" />
                            <Text style={styles.loadingText}>Preparing high-quality video...</Text>
                            <Text style={styles.bufferingSubtext}>Optimization in progress</Text>
                        </View>
                    </View>
                )}

                <View style={[styles.videoContainer, isFullScreen && styles.fullScreenContainer]}>
                    {videoId ? (
                        <View style={styles.youtubeWrapper}>
                            <YoutubePlayer
                                height={isFullScreen ? height : width * 9 / 16}
                                width={isFullScreen ? width : width}
                                play={playing}
                                videoId={videoId}
                                onChangeState={onStateChange}
                                onReady={onReadyHandler}
                                onFullScreenChange={onFullScreenChange}
                                initialPlayerParams={{
                                    controls: 1, // Enable standard YouTube controls
                                    modestbranding: 1, // Minimize YouTube branding
                                    rel: 0,
                                    playsinline: 1,
                                    fs: 1, // Enable Fullscreen button
                                    iv_load_policy: 3, // Disable annotations for faster load
                                }}
                                // Enable Hardware Acceleration for WebView
                                webViewProps={{
                                    allowsInlineMediaPlayback: true,
                                    javaScriptEnabled: true,
                                    domStorageEnabled: true,
                                    renderToHardwareTextureAndroid: true,
                                    androidLayerType: 'hardware',
                                    startInLoadingState: false, // Performance tweak
                                }}
                                // Performance: Ensure player is only as large as needed
                                forceAndroidAutoplay={true}
                            />
                        </View>
                    ) : (
                        <Video
                            style={styles.video}
                            source={{ uri: fullVideoUrl }}
                            useNativeControls
                            resizeMode={ResizeMode.CONTAIN}
                            isLooping={false}
                            shouldPlay={true}
                            onLoadStart={() => setIsReady(false)}
                            onLoad={() => setIsReady(true)}
                            onError={(e) => console.log('Video Error:', e)}
                        />
                    )}
                </View>
            </View>

            {/* Footer / Info Section */}
            {!isFullScreen && isReady && (
                <View style={styles.infoSection}>
                    <View style={styles.qualityBadge}>
                        <Text style={styles.qualityText}>Full HD Playback</Text>
                    </View>
                    <Text style={styles.nowPlaying}>Now Playing: {title}</Text>
                    <Text style={[styles.subText, { color: '#94a3b8' }]}>Please wait for buffering if your internet is slow.</Text>
                </View>
            )}
        </View>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: 'black',
    },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        padding: 15,
        paddingTop: 40,
        backgroundColor: 'rgba(0,0,0,0.9)',
        borderBottomWidth: 1,
        borderBottomColor: '#334155',
    },
    closeButton: {
        padding: 10,
        marginRight: 10,
    },
    closeText: {
        color: 'white',
        fontSize: 16,
        fontWeight: 'bold',
        fontFamily: 'NotoSans-Bold',
    },
    title: {
        color: 'white',
        fontSize: 16,
        flex: 1,
        fontFamily: 'NotoSans-Bold',
    },
    timerOverlay: {
        position: 'absolute',
        top: 40,
        right: 20,
        backgroundColor: 'rgba(0,0,0,0.7)',
        padding: 8,
        borderRadius: 20,
        flexDirection: 'row',
        alignItems: 'center',
        zIndex: 2000,
        borderWidth: 1,
        borderColor: 'rgba(255,255,255,0.2)',
    },
    timerOverlayFullScreen: {
        top: 20,
        right: 50,
    },
    timerText: {
        color: 'white',
        fontWeight: 'bold',
        marginRight: 10,
        fontVariant: ['tabular-nums'],
        fontFamily: 'NotoSans-Bold',
    },
    finishBtn: {
        backgroundColor: '#7e22ce',
        paddingHorizontal: 10,
        paddingVertical: 5,
        borderRadius: 10,
    },
    finishBtnText: {
        color: 'white',
        fontWeight: 'bold',
        fontSize: 12,
        fontFamily: 'NotoSans-Bold',
    },
    videoWrapper: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
        backgroundColor: '#000',
    },
    fullScreenWrapper: {
        position: 'absolute',
        top: 0,
        left: 0,
        bottom: 0,
        right: 0,
        zIndex: 999,
    },
    loadingOverlay: {
        ...StyleSheet.absoluteFillObject,
        zIndex: 10,
        backgroundColor: '#000',
        justifyContent: 'center',
        alignItems: 'center',
    },
    loadingInfo: {
        alignItems: 'center',
        padding: 20,
    },
    loadingText: {
        color: 'white',
        fontSize: 18,
        fontWeight: 'bold',
        marginTop: 20,
        textAlign: 'center',
        fontFamily: 'NotoSans-Bold',
    },
    bufferingSubtext: {
        color: '#94a3b8',
        fontSize: 13,
        marginTop: 8,
        fontFamily: 'NotoSans-Regular',
    },
    videoContainer: {
        width: '100%',
        aspectRatio: 16 / 9,
        justifyContent: 'center',
        alignItems: 'center',
    },
    fullScreenContainer: {
        width: '100%',
        height: '100%',
        aspectRatio: undefined,
    },
    youtubeWrapper: {
        width: '100%',
        height: '100%',
        alignItems: 'center',
        justifyContent: 'center',
    },
    video: {
        width: '100%',
        height: '100%',
    },
    infoSection: {
        padding: 24,
        backgroundColor: '#0f172a',
        borderTopLeftRadius: 30,
        borderTopRightRadius: 30,
        marginTop: -20,
        flex: 1,
    },
    qualityBadge: {
        backgroundColor: '#334155',
        alignSelf: 'flex-start',
        paddingHorizontal: 12,
        paddingVertical: 4,
        borderRadius: 8,
        marginBottom: 16,
    },
    qualityText: {
        color: '#f8fafc',
        fontSize: 11,
        fontWeight: 'bold',
        letterSpacing: 0.5,
    },
    nowPlaying: {
        color: 'white',
        fontSize: 20,
        fontWeight: '800',
        fontFamily: 'NotoSans-Bold',
        marginBottom: 8,
    },
    subText: {
        fontSize: 14,
        fontFamily: 'NotoSans-Regular',
    }
});

export default VideoPlayerScreen;
