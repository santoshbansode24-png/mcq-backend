import * as FileSystem from 'expo-file-system';
import * as Sharing from 'expo-sharing';
import { Alert, Platform } from 'react-native';

/**
 * Downloads a file from a URL and shares/saves it.
 * @param {string} url - The URL of the file to download.
 * @param {string} title - The title of the file (used for the filename).
 * @param {function} setLoading - Optional callback to set loading state (true/false).
 */
export const downloadFile = async (url, title, setLoading = null) => {
    if (!url) {
        Alert.alert("Error", "No download URL provided.");
        return;
    }

    if (setLoading) setLoading(true);

    try {
        let downloadUrl = url;

        // Handle Google Drive URLs
        if (url.includes('drive.google.com')) {
            const match = url.match(/\/d\/([a-zA-Z0-9_-]+)/);
            if (match && match[1]) {
                downloadUrl = `https://drive.google.com/uc?id=${match[1]}&export=download`;
            }
        }

        // Generate a safe filename
        const safeTitle = (title || 'document').replace(/[^a-z0-9]/gi, '_').toLowerCase();
        // Assume PDF for now as that's the primary use case, or try to infer from URL/Headers (complex)
        // Ideally the backend should provide the extension, but we'll default to .pdf if not present
        let extension = '.pdf';
        if (downloadUrl.toLowerCase().endsWith('.jpg')) extension = '.jpg';
        if (downloadUrl.toLowerCase().endsWith('.png')) extension = '.png';

        const fileUri = `${FileSystem.documentDirectory}${safeTitle}${extension}`;

        console.log(`[DownloadUtils] Downloading ${downloadUrl} to ${fileUri}`);

        // Download the file
        const downloadRes = await FileSystem.downloadAsync(downloadUrl, fileUri);

        if (downloadRes.status === 200) {
            console.log('[DownloadUtils] Download complete:', downloadRes.uri);

            if (await Sharing.isAvailableAsync()) {
                await Sharing.shareAsync(downloadRes.uri);
            } else {
                Alert.alert("Success", "File downloaded to app storage.");
            }
        } else {
            throw new Error(`Download failed with status ${downloadRes.status}`);
        }

    } catch (error) {
        console.error("[DownloadUtils] Error:", error);
        Alert.alert("Error", "Failed to download file. Please check your internet connection.");
    } finally {
        if (setLoading) setLoading(false);
    }
};
