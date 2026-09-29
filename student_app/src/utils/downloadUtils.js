import * as FileSystem from 'expo-file-system/legacy';
import * as Sharing from 'expo-sharing';
import { Alert, Platform } from 'react-native';
import NetInfo from '@react-native-community/netinfo';

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

    // specific check for local files
    if (url.startsWith('file://')) {
        if (await Sharing.isAvailableAsync()) {
            await Sharing.shareAsync(url, {
                UTI: '.pdf',
                mimeType: 'application/pdf',
            });
        }
        return;
    }

    if (setLoading) setLoading(true);

    try {
        let downloadUrl = url;

        // Handle Google Drive URLs - REMOVED AS PER USER REQUEST
        // The user strictly wants to fetch files from AWS/Server directly.
        // if (url.includes('drive.google.com')) { ... }

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

const NOTES_STORAGE_DIR = `${FileSystem.documentDirectory}veeru_notes/`;

/**
 * Checks if a note is already downloaded and saved permanently on this device.
 * @param {string} url - Remote URL
 * @param {string} title - Title of the note
 * @returns {Promise<string|null>} Local file URI if exists, otherwise null
 */
export const isNoteCachedLocally = async (url, title) => {
    if (!url) return null;
    if (url.startsWith('file://')) return url;

    try {
        const safeTitle = (title || 'doc').replace(/[^a-z0-9]/gi, '_').toLowerCase();
        const cleanName = url.split('/').pop().split('?')[0] || '';
        const urlHash = cleanName.length > 15 ? cleanName.slice(-15) : cleanName;
        const filename = `${safeTitle}_${urlHash}.pdf`;
        const fileUri = `${NOTES_STORAGE_DIR}${filename}`;

        const fileInfo = await FileSystem.getInfoAsync(fileUri);
        if (fileInfo.exists && fileInfo.size > 500) {
            return fileUri;
        }

        // Check legacy cache directory fallback and migrate if found
        const legacyDir = `${FileSystem.cacheDirectory}veeru_pdf_cache_v2/`;
        const legacyFileUri = `${legacyDir}${filename}`;
        const legacyInfo = await FileSystem.getInfoAsync(legacyFileUri);
        if (legacyInfo.exists && legacyInfo.size > 500) {
            await FileSystem.makeDirectoryAsync(NOTES_STORAGE_DIR, { intermediates: true }).catch(() => {});
            await FileSystem.copyAsync({ from: legacyFileUri, to: fileUri }).catch(() => {});
            return fileUri;
        }

        return null;
    } catch {
        return null;
    }
};

/**
 * Checks for a cached file. If missing, downloads it to permanent storage.
 * Returns the local URI to be used in the PDF Viewer.
 * @param {string} url - Remote URL (AWS, Server, etc.)
 * @param {string} title - Title for filename generation
 * @param {function} onProgress - Callback (0-1) for progress bar
 * @returns {Promise<string>} Local file URI (file://...)
 */
export const getCachedFile = async (url, title, onProgress = null, silent = false) => {
    if (!url) return null;

    // If it's already a local file, return it immediately
    if (url.startsWith('file://')) {
        return url;
    }

    try {
        // 1. Check if already saved in permanent storage
        const existingLocalUri = await isNoteCachedLocally(url, title);
        if (existingLocalUri) {
            console.log('[Cache] Permanent Hit:', existingLocalUri);
            return existingLocalUri;
        }

        // 2. If missing, check network connectivity before attempting download
        const netInfo = await NetInfo.fetch();
        if (!netInfo.isConnected) {
            const offlineMsg = 'This note has not been downloaded yet. Please connect to the internet once to save it for offline reading.';
            if (!silent) {
                Alert.alert('Offline Mode', offlineMsg);
            }
            throw new Error(offlineMsg);
        }

        const safeTitle = (title || 'doc').replace(/[^a-z0-9]/gi, '_').toLowerCase();
        const cleanName = url.split('/').pop().split('?')[0] || '';
        const urlHash = cleanName.length > 15 ? cleanName.slice(-15) : cleanName;
        const filename = `${safeTitle}_${urlHash}.pdf`;
        const fileUri = `${NOTES_STORAGE_DIR}${filename}`;

        // Ensure permanent directory exists
        const dirInfo = await FileSystem.getInfoAsync(NOTES_STORAGE_DIR);
        if (!dirInfo.exists) {
            await FileSystem.makeDirectoryAsync(NOTES_STORAGE_DIR, { intermediates: true });
        }

        console.log('[Cache] Downloading to permanent storage:', url);

        const downloadResumable = FileSystem.createDownloadResumable(
            url,
            fileUri,
            {},
            (downloadProgress) => {
                if (onProgress) {
                    const progress = downloadProgress.totalBytesWritten / downloadProgress.totalBytesExpectedToWrite;
                    onProgress(progress);
                }
            }
        );

        const { uri } = await downloadResumable.downloadAsync();

        // 3. Verify the downloaded file
        const downloadedFileInfo = await FileSystem.getInfoAsync(uri);

        if (!downloadedFileInfo.exists || downloadedFileInfo.size < 500) {
            const msg = `[Cache] Downloaded file is too small (${downloadedFileInfo.size} bytes). Deleting.`;
            if (silent) console.log(msg); else console.error(msg);

            await FileSystem.deleteAsync(uri, { idempotent: true });
            throw new Error("Downloaded file is invalid or empty.");
        }

        // 4. Magic number check: verify it's a real PDF
        const fileHeader = await FileSystem.readAsStringAsync(uri, {
            length: 100,
            position: 0,
            encoding: 'utf8'
        });

        if (!fileHeader.includes('%PDF')) {
            const msg = `[Cache] Invalid file format. Content starts with: ${fileHeader.substring(0, 50)}`;
            if (silent) console.log(msg); else console.error(msg);

            await FileSystem.deleteAsync(uri, { idempotent: true });

            if (fileHeader.includes('<html') || fileHeader.includes('<!DOCTYPE') || fileHeader.includes('Error:')) {
                throw new Error(`Server Error: ${fileHeader.substring(0, 50)}...`);
            }
            throw new Error("Downloaded file is not a valid PDF.");
        }

        console.log('[Cache] ✅ Successfully saved permanently:', uri);
        return uri;

    } catch (e) {
        if (!silent) {
            console.error('[Cache] Critical Error:', e);
            Alert.alert("Download Error", e.message || "Failed to download note for offline viewing.");
        } else {
            console.log('[Cache] Silent Error:', e.message);
        }
        throw e;
    }
};
