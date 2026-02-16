import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';
import { Alert, Platform } from 'react-native';

/**
 * Downloads a file from a URL and shares/saves it.
 * @param {string} url - The URL of the file to download.
 * @param {string} title - The title of the file (used for the filename).
 * @param {function} setLoading - Optional callback to set loading state (true/false).
 * @param {boolean} autoOpen - If true, automatically opens the file after download.
 */
export const downloadFile = async (url, title, setLoading = null, autoOpen = false) => {
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

            // Verify file validity
            const fileInfo = await FileSystem.getInfoAsync(downloadRes.uri);
            if (!fileInfo.exists || fileInfo.size < 100) {
                Alert.alert("Error", "Downloaded file seems corrupt or empty.");
                console.error("[DownloadUtils] Corrupt file:", fileInfo);
                return;
            }
            console.log(`[DownloadUtils] File Size: ${fileInfo.size} bytes`);

            // Always try to open the file using the system's default app
            if (await Sharing.isAvailableAsync()) {
                await Sharing.shareAsync(downloadRes.uri, {
                    UTI: '.pdf',
                    mimeType: 'application/pdf',
                });
            } else {
                Alert.alert("Success", "File downloaded successfully. Please check your downloads folder.");
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
