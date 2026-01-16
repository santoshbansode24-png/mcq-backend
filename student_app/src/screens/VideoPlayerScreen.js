import React, { useState, useCallback } from 'react';
import { View, StyleSheet, TouchableOpacity, Text, StatusBar, Dimensions } from 'react-native';
import YoutubePlayer from 'react-native-youtube-iframe';
import { Video, ResizeMode } from 'expo-av';
import * as ScreenOrientation from 'expo-screen-orientation';
import { BASE_URL } from '../api/config';

const { width } = Dimensions.get('window');

const VideoPlayerScreen = ({ route, navigation }) => {
    const { videoUrl, title } = route.params || {};
    const [playing, setPlaying] = useState(true);
    const [isFullScreen, setIsFullScreen] = useState(false);

    const onStateChange = useCallback((state) => {
        if (state === "ended") {
            setPlaying(false);
        }
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

    const videoId = getVideoId(videoUrl);

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
            <StatusBar hidden={isFullScreen} />

            {/* Header - Only visible in Portrait */}
            {!isFullScreen && (
                <View style={styles.header}>
                    <TouchableOpacity onPress={() => navigation.goBack()} style={styles.closeButton}>
                        <Text style={styles.closeText}>✕ Close</Text>
                    </TouchableOpacity>
                    <Text style={styles.title} numberOfLines={1}>{title || 'Video Player'}</Text>
                </View>
            )}

            <View style={[styles.videoContainer, isFullScreen && styles.fullScreenContainer]}>
                {videoId ? (
                    <View style={styles.youtubeWrapper}>
                        <YoutubePlayer
                            height={isFullScreen ? '100%' : width * 9 / 16}
                            width={isFullScreen ? '100%' : width}
                            play={playing}
                            videoId={videoId}
                            onChangeState={onStateChange}
                            onFullScreenChange={onFullScreenChange}
                            initialPlayerParams={{
                                controls: 1, // Enable standard YouTube controls
                                modestbranding: 0, // Show YouTube branding
                                rel: 0,
                                playsinline: 1,
                                fs: 1, // Enable Fullscreen button
                            }}
                            // We refrain from aggressive WebView blocking to allow standard behavior
                            webViewProps={{
                                allowsInlineMediaPlayback: true,
                                javaScriptEnabled: true,
                                domStorageEnabled: true,
                            }}
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
                    />
                )}
            </View>
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
        backgroundColor: 'rgba(0,0,0,0.8)',
    },
    closeButton: {
        padding: 10,
        marginRight: 10,
    },
    closeText: {
        color: 'white',
        fontSize: 16,
        fontWeight: 'bold',
    },
    title: {
        color: 'white',
        fontSize: 16,
        flex: 1,
    },
    videoContainer: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
    },
    fullScreenContainer: {
        position: 'absolute',
        top: 0,
        left: 0,
        bottom: 0,
        right: 0,
        zIndex: 999,
        backgroundColor: 'black',
    },
    youtubeWrapper: {
        width: '100%',
        alignItems: 'center',
        justifyContent: 'center',
    },
    video: {
        width: '100%',
        height: 300,
    },
});

export default VideoPlayerScreen;