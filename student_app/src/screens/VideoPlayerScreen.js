import React, { useState, useCallback, useMemo } from 'react';
import { View, StyleSheet, TouchableOpacity, Text, StatusBar, BackHandler, useWindowDimensions, Pressable } from 'react-native';
import YoutubePlayer from 'react-native-youtube-iframe';
import { Video, ResizeMode, VideoFullscreenUpdate } from 'expo-av';
import * as ScreenOrientation from 'expo-screen-orientation';
import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { API_URL, BASE_URL } from '../api/config';

const VideoPlayerScreen = ({ route, navigation }) => {
    const { width } = useWindowDimensions();
    const { videoUrl, title, activeTask } = route.params || {};
    const [playing, setPlaying] = useState(true);
    const [isFullScreen, setIsFullScreen] = useState(false);
    const [playbackRate, setPlaybackRate] = useState(1);

    const onStateChange = useCallback((state) => {
        if (state === 'ended') setPlaying(false);
    }, []);

    const onFullScreenChange = useCallback((fullScreen) => {
        setIsFullScreen(fullScreen);
        if (fullScreen) {
            ScreenOrientation.lockAsync(ScreenOrientation.OrientationLock.LANDSCAPE);
        } else {
            ScreenOrientation.lockAsync(ScreenOrientation.OrientationLock.PORTRAIT);
        }
    }, []);

    // Timer State
    const [taskTimer, setTaskTimer] = useState(0);
    const [isTaskActive, setIsTaskActive] = useState(false);

    React.useEffect(() => {
        if (activeTask && !isTaskActive) {
            setTaskTimer(activeTask.duration_minutes * 60);
            setIsTaskActive(true);
        }
    }, [activeTask, isTaskActive]);

    // Hardware back button
    React.useEffect(() => {
        const backAction = () => {
            if (isFullScreen) {
                ScreenOrientation.lockAsync(ScreenOrientation.OrientationLock.PORTRAIT);
                setIsFullScreen(false);
                return true;
            }
            navigation.goBack();
            return true;
        };
        const backHandler = BackHandler.addEventListener('hardwareBackPress', backAction);
        return () => backHandler.remove();
    }, [isFullScreen, navigation]);

    // Reset orientation on unmount
    React.useEffect(() => {
        return () => {
            ScreenOrientation.lockAsync(ScreenOrientation.OrientationLock.PORTRAIT);
        };
    }, []);

    // Countdown
    React.useEffect(() => {
        let interval = null;
        if (isTaskActive && taskTimer > 0) {
            interval = setInterval(() => setTaskTimer(p => p - 1), 1000);
        } else if (taskTimer === 0 && isTaskActive) {
            finishTask();
        }
        return () => clearInterval(interval);
    }, [isTaskActive, taskTimer]);

    const finishTask = async () => {
        setIsTaskActive(false);
        try {
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

    // Extract YouTube video ID
    const getVideoId = (url) => {
        if (!url) return null;
        const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=)([^#&?]*).*/;
        const match = url.match(regExp);
        if (match && match[2]) {
            const cleanId = match[2].replace(/[^a-zA-Z0-9_-]/g, '');
            return cleanId.length >= 11 ? cleanId.substring(0, 11) : null;
        }
        return null;
    };

    const videoId = useMemo(() => getVideoId(videoUrl), [videoUrl]);

    const getFullVideoUrl = (url) => {
        if (!url) return null;
        if (url.startsWith('http')) return url;
        return `${BASE_URL}/${url.startsWith('/') ? url.substring(1) : url}`;
    };
    const fullVideoUrl = getFullVideoUrl(videoUrl);

    return (
        <View style={styles.container}>
            <StatusBar barStyle="light-content" backgroundColor="black" />

            {/* Header */}
            {!isFullScreen && (
                <View style={styles.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={styles.closeButton}>
                        <Text style={styles.closeText}>← Back</Text>
                    </TouchableOpacity>
                    <Text style={styles.title} numberOfLines={1}>{title || 'Video'}</Text>

                    {isTaskActive && (
                        <View style={styles.timerBadge}>
                            <Text style={styles.timerText}>⏳ {formatTimer(taskTimer)}</Text>
                        </View>
                    )}
                </View>
            )}

            {/* Video Player */}
            <Pressable 
                onLongPress={() => setPlaybackRate(2)}
                onPressOut={() => setPlaybackRate(1)}
                delayLongPress={400}
                style={isFullScreen ? styles.fullScreenWrapper : styles.videoWrapper}
            >
                <View pointerEvents="none" style={StyleSheet.absoluteFill}>
                    {/* This transparent layer catches gestures for 2x speed without blocking the player's internal logic 
                        Actually, it's better to just use the Pressable around it.
                    */}
                </View>
                {videoId ? (
                    <YoutubePlayer
                        height={width * 9 / 16}
                        width={width}
                        play={playing}
                        videoId={videoId}
                        onChangeState={onStateChange}
                        onFullScreenChange={onFullScreenChange}
                        playbackRate={playbackRate}
                        initialPlayerParams={{ controls: 1, rel: 0, playsinline: 1 }}
                        webViewProps={{
                            allowsInlineMediaPlayback: true,
                            javaScriptEnabled: true,
                            domStorageEnabled: true,
                        }}
                        forceAndroidAutoplay={true}
                    />
                ) : (
                    <Video
                        style={styles.video}
                        source={{ uri: fullVideoUrl }}
                        useNativeControls
                        resizeMode={ResizeMode.CONTAIN}
                        shouldPlay={true}
                        rate={playbackRate}
                        shouldCorrectPitch={true}
                        onFullscreenUpdate={async ({ fullscreenUpdate }) => {
                            if (fullscreenUpdate === VideoFullscreenUpdate.PLAYER_WILL_PRESENT) {
                                setIsFullScreen(true);
                                await ScreenOrientation.lockAsync(ScreenOrientation.OrientationLock.LANDSCAPE);
                            } else if (fullscreenUpdate === VideoFullscreenUpdate.PLAYER_DID_DISMISS) {
                                setIsFullScreen(false);
                                await ScreenOrientation.lockAsync(ScreenOrientation.OrientationLock.PORTRAIT);
                            }
                        }}
                    />
                )}

                {/* Speed Indicator Overlay */}
                {playbackRate > 1 && (
                    <View style={styles.speedBadgeOverlay}>
                        <Text style={styles.speedBadgeText}>⏩ 2x Speed</Text>
                    </View>
                )}
            </Pressable>

            {/* Simple Now Playing label */}
            {!isFullScreen && (
                <View style={styles.infoSection}>
                    <Text style={styles.nowPlaying} numberOfLines={2}>{title || 'Now Playing'}</Text>
                    {isTaskActive && (
                        <TouchableOpacity onPress={finishTask} style={styles.finishBtn}>
                            <Text style={styles.finishBtnText}>✓ Mark Task Done</Text>
                        </TouchableOpacity>
                    )}
                </View>
            )}
        </View>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#000' },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        paddingHorizontal: 16,
        paddingTop: 44,
        paddingBottom: 12,
        backgroundColor: '#000',
    },
    closeButton: { paddingRight: 12 },
    closeText: { color: '#fff', fontSize: 16, fontWeight: 'bold' },
    title: { flex: 1, color: '#fff', fontSize: 15, fontWeight: '600' },
    timerBadge: {
        backgroundColor: 'rgba(255,255,255,0.15)',
        paddingHorizontal: 10,
        paddingVertical: 4,
        borderRadius: 12,
        marginLeft: 8,
    },
    timerText: { color: '#fff', fontSize: 13, fontWeight: 'bold' },
    videoWrapper: {
        width: '100%',
        aspectRatio: 16 / 9,
        backgroundColor: '#000',
    },
    fullScreenWrapper: {
        position: 'absolute',
        top: 0, left: 0, right: 0, bottom: 0,
        zIndex: 999,
    },
    video: { width: '100%', height: '100%' },
    infoSection: {
        padding: 16,
        backgroundColor: '#111',
    },
    nowPlaying: {
        color: '#e2e8f0',
        fontSize: 16,
        fontWeight: '600',
        marginBottom: 12,
    },
    finishBtn: {
        backgroundColor: '#7e22ce',
        paddingHorizontal: 16,
        paddingVertical: 10,
        borderRadius: 10,
        alignSelf: 'flex-start',
    },
    finishBtnText: { color: '#fff', fontWeight: 'bold', fontSize: 14 },
    speedBadgeOverlay: {
        position: 'absolute',
        top: 20,
        alignSelf: 'center',
        backgroundColor: 'rgba(0,0,0,0.6)',
        paddingHorizontal: 12,
        paddingVertical: 6,
        borderRadius: 20,
        zIndex: 1000,
    },
    speedBadgeText: {
        color: '#fff',
        fontSize: 14,
        fontWeight: 'bold',
    },
});

export default VideoPlayerScreen;
