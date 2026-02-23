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
/**
 * Checks for a cached file. If missing, downloads it.
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
        // 1. Generate Safe Filename (Use Hash or cleanup)
        // We use a simple strategy: sanitize title + hash of URL
        const safeTitle = (title || 'doc').replace(/[^a-z0-9]/gi, '_').toLowerCase();
        const urlHash = url.split('/').pop().split('?')[0].substr(-10); // Simple uniqueness
        const filename = `${safeTitle}_${urlHash}.pdf`;

        // 2. Setup Cache Directory
        // Changed to v2 to invalidate old/corrupt cache
        const cacheDir = `${FileSystem.cacheDirectory}veeru_pdf_cache_v2/`;
        const fileUri = `${cacheDir}${filename}`;

        // Ensure directory exists
        const dirInfo = await FileSystem.getInfoAsync(cacheDir);
        if (!dirInfo.exists) {
            await FileSystem.makeDirectoryAsync(cacheDir, { intermediates: true });
        }

        // 3. Check if file already exists
        const fileInfo = await FileSystem.getInfoAsync(fileUri);
        if (fileInfo.exists && fileInfo.size > 100) {
            console.log('[Cache] Hit:', fileUri);
            return fileUri;
        }

        // 4. Download if missing
        console.log('[Cache] Miss. Downloading:', url);

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

        // 5. Verify the downloaded file
        const downloadedFileInfo = await FileSystem.getInfoAsync(uri);

        if (!downloadedFileInfo.exists || downloadedFileInfo.size < 500) {
            const msg = `[Cache] Downloaded file is too small (${downloadedFileInfo.size} bytes). Deleting.`;
            if (silent) console.log(msg); else console.error(msg);

            await FileSystem.deleteAsync(uri, { idempotent: true });
            throw new Error("Downloaded file is invalid or empty.");
        }

        // 6. MAGIC NUMBER CHECK: Read the beginning of the file to see if it claims to be a PDF
        // This stops us from caching HTML error pages (404/500) as PDFs
        const fileHeader = await FileSystem.readAsStringAsync(uri, {
            length: 100, // Read a bit more to catch HTML tags
            position: 0,
            encoding: 'utf8'
        });

        if (!fileHeader.includes('%PDF')) {
            const msg = `[Cache] Invalid file format. Does not start with %PDF. Content starts with: ${fileHeader.substring(0, 50)}`;
            if (silent) console.log(msg); else console.error(msg);

            await FileSystem.deleteAsync(uri, { idempotent: true });

            // Extract detailed error if it looks like HTML
            if (fileHeader.includes('<html') || fileHeader.includes('<!DOCTYPE') || fileHeader.includes('Error:')) {
                throw new Error(`Server Error: ${fileHeader.substring(0, 50)}...`);
            }
            throw new Error("Downloaded file is not a valid PDF.");
        }

        console.log('[Cache] ✅ Successfully cached:', uri);
        return uri;

    } catch (e) {
        if (!silent) {
            console.error('[Cache] Critical Error:', e);
            Alert.alert("Download Error", e.message || "Failed to download file for offline viewing.");
        } else {
            console.log('[Cache] Silent Error:', e.message);
        }
        throw e;
    }
};
