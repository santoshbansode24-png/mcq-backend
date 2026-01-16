import React from 'react';
import { View, StyleSheet, TouchableOpacity, Text, StatusBar, ActivityIndicator, Platform, Alert } from 'react-native';
import { WebView } from 'react-native-webview';
import * as FileSystem from 'expo-file-system';
import * as Sharing from 'expo-sharing';
import { Ionicons } from '@expo/vector-icons'; // Assuming Ionicons is available, if not use Text emoji

const PDFViewerScreen = ({ navigation, route }) => {
    const { url, title } = route.params || {};
    const [downloading, setDownloading] = React.useState(false);

    const getViewerUrl = () => {
        if (Platform.OS === 'android') {
            if (url.includes('drive.google.com')) {
                const match = url.match(/\/d\/([a-zA-Z0-9_-]+)/);
                if (match && match[1]) {
                    return `https://drive.google.com/file/d/${match[1]}/preview`;
                }
            }
            return `https://docs.google.com/gview?embedded=true&url=${encodeURIComponent(url)}`;
        }
        return url;
    };

    const handleDownload = async () => {
        setDownloading(true);
        try {
            // Determine Download URL
            let downloadUrl = url;
            if (url.includes('drive.google.com')) {
                const match = url.match(/\/d\/([a-zA-Z0-9_-]+)/);
                if (match && match[1]) {
                    downloadUrl = `https://drive.google.com/uc?id=${match[1]}&export=download`;
                }
            }

            // Sanitize filename
            const cleanTitle = (title || 'document').replace(/[^a-z0-9]/gi, '_').toLowerCase();
            const fileUri = `${FileSystem.documentDirectory}${cleanTitle}.pdf`;

            // Download
            const downloadRes = await FileSystem.downloadAsync(downloadUrl, fileUri);

            if (downloadRes.status === 200) {
                // Share / Save
                if (await Sharing.isAvailableAsync()) {
                    await Sharing.shareAsync(downloadRes.uri);
                } else {
                    Alert.alert("Success", "File downloaded but sharing is not available on this device.");
                }
            } else {
                throw new Error("Download failed");
            }

        } catch (error) {
            console.error("Download error:", error);
            Alert.alert("Download Failed", "Could not download the file. Please try again.");
        } finally {
            setDownloading(false);
        }
    };

    return (
        <View style={styles.container}>
            <StatusBar barStyle="dark-content" backgroundColor="#fff" />
            <View style={styles.header}>
                <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
                    <Text style={styles.backButtonText}>←</Text>
                </TouchableOpacity>
                <Text style={styles.headerTitle} numberOfLines={1}>{title || 'Document'}</Text>

                <TouchableOpacity
                    onPress={handleDownload}
                    style={styles.downloadButton}
                    disabled={downloading}
                >
                    {downloading ? (
                        <ActivityIndicator size="small" color="#4f46e5" />
                    ) : (
                        <Text style={{ fontSize: 24 }}>📥</Text>
                    )}
                </TouchableOpacity>
            </View>

            <View style={styles.contentContainer}>
                <WebView
                    source={{ uri: getViewerUrl() }}
                    style={{ flex: 1 }}
                    startInLoadingState={true}
                    renderLoading={() => <ActivityIndicator size="large" color="#4f46e5" style={{ position: 'absolute', top: '50%', left: '50%' }} />}
                />
            </View>
        </View>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#fff' },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        padding: 16,
        paddingTop: Platform.OS === 'android' ? 10 : 50,
        borderBottomWidth: 1,
        borderBottomColor: '#e2e8f0',
        backgroundColor: '#fff'
    },
    backButton: { padding: 8, marginRight: 8 },
    backButtonText: { fontSize: 24, color: '#333' },
    headerTitle: { fontSize: 18, fontWeight: 'bold', color: '#1e293b', flex: 1 },
    downloadButton: { padding: 8 },
    openButton: { backgroundColor: '#4f46e5', paddingHorizontal: 16, paddingVertical: 8, borderRadius: 20 },
    openButtonText: { color: 'white', fontWeight: 'bold', fontSize: 14 },
    contentContainer: { flex: 1 },
    messageContainer: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
        padding: 32,
    },
    messageIcon: { fontSize: 64, marginBottom: 16 },
    messageTitle: { fontSize: 20, fontWeight: 'bold', color: '#1e293b', marginBottom: 8 },
    messageText: { fontSize: 16, color: '#64748b', textAlign: 'center', marginBottom: 24 },
    reopenButton: {
        backgroundColor: '#4f46e5',
        paddingHorizontal: 32,
        paddingVertical: 12,
        borderRadius: 24
    },
    reopenButtonText: { color: 'white', fontWeight: 'bold', fontSize: 16 },
});

export default PDFViewerScreen;