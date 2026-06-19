import React from 'react';
import { View, StyleSheet, TouchableOpacity, Text, StatusBar, ActivityIndicator, Platform, Alert, Linking } from 'react-native';
import { WebView } from 'react-native-webview';
import * as FileSystem from 'expo-file-system/legacy'; // Use legacy to match downloadUtils

import { downloadFile } from '../utils/downloadUtils';
import { Ionicons } from '@expo/vector-icons';

const PDFViewerScreen = ({ navigation, route }) => {
  const { url, title } = route.params || {};
  const [downloading, setDownloading] = React.useState(false);
  const [loadingFile, setLoadingFile] = React.useState(true);
  const [pdfBase64, setPdfBase64] = React.useState(null);
  const [useGoogleDocs, setUseGoogleDocs] = React.useState(false);
  const [error, setError] = React.useState(null);
  const [webViewLoaded, setWebViewLoaded] = React.useState(false);
  const webViewRef = React.useRef(null);

  // ROBUST CHECK: If it looks like a file, treat it as local.
  const isLocalFile = url && (url.startsWith('file:') || url.startsWith('/') || (!url.startsWith('http') && !url.startsWith('https')));

  const loadPdfData = async () => {
    setError(null);
    setLoadingFile(true);
    setPdfBase64(null);
    setWebViewLoaded(false);
    try {
      if (isLocalFile) {
        console.log("Reading local file as Base64...");
        const base64 = await FileSystem.readAsStringAsync(url, { encoding: 'base64' });
        setPdfBase64(base64);
      } else {
        console.log("Downloading remote PDF from:", url);
        const tempFileUri = `${FileSystem.cacheDirectory}temp_pdf_${Date.now()}.pdf`;
        const downloadResult = await FileSystem.downloadAsync(url, tempFileUri);
        
        console.log("Download status:", downloadResult.status);
        
        // Check HTTP status
        if (downloadResult.status !== 200) {
          FileSystem.deleteAsync(tempFileUri, { idempotent: true }).catch(() => {});
          throw new Error(`Server returned status ${downloadResult.status}.\n\nThe file may not exist on the server.\n\nURL: ${url}`);
        }

        // Check Content-Type header
        const contentType = (downloadResult.headers?.['content-type'] || '').toLowerCase();
        if (contentType.includes('text/html')) {
          FileSystem.deleteAsync(tempFileUri, { idempotent: true }).catch(() => {});
          throw new Error(`Server returned an HTML page instead of a PDF.\n\nThe file may have moved or the URL is incorrect.\n\nURL: ${url}`);
        }

        // Verify PDF header bytes (%PDF)
        const headerBytes = await FileSystem.readAsStringAsync(tempFileUri, { 
          encoding: 'utf8', 
          length: 5, 
          position: 0 
        }).catch(() => '');
        
        if (headerBytes && !headerBytes.startsWith('%PDF')) {
          FileSystem.deleteAsync(tempFileUri, { idempotent: true }).catch(() => {});
          throw new Error(`The downloaded file is not a valid PDF.\n\nThe teacher may need to re-upload the file.\n\nURL: ${url}`);
        }

        console.log("Downloaded valid PDF, checking size...");
        const fileInfo = await FileSystem.getInfoAsync(tempFileUri);
        const fileSize = fileInfo.size || 0;
        console.log("File size in bytes:", fileSize);

        if (fileSize >= 5 * 1024 * 1024) { // 5MB limit
            console.log("File is too large for memory buffer. Using Google Docs Viewer...");
            setUseGoogleDocs(true);
            FileSystem.deleteAsync(tempFileUri, { idempotent: true }).catch(() => {});
        } else {
            console.log("Reading PDF as Base64...");
            const base64 = await FileSystem.readAsStringAsync(tempFileUri, { encoding: 'base64' });
            setPdfBase64(base64);
            setUseGoogleDocs(false);
            FileSystem.deleteAsync(tempFileUri, { idempotent: true }).catch(() => {});
        }
      }
    } catch (err) {
      console.error("Error loading PDF file:", err);
      setError(err.message || "Could not load PDF.");
    }
    setLoadingFile(false);
  };

  React.useEffect(() => {
    if (url) {
      loadPdfData();
    } else {
      setError("No URL provided to viewer.");
      setLoadingFile(false);
    }
  }, [url]);

  // Send data to WebView whenever pdfBase64 changes OR WebView finishes loading
  React.useEffect(() => {
    if (pdfBase64 && webViewLoaded && webViewRef.current) {
      console.log("Triggering postMessage to WebView...");
      webViewRef.current.postMessage(JSON.stringify({
        type: 'init_data',
        data: pdfBase64
      }));
    }
  }, [pdfBase64, webViewLoaded]);

  const handleWebViewLoad = () => {
    console.log("WebView loaded.");
    setWebViewLoaded(true);
  };

  const loaderScript = `
          document.addEventListener('message', function(event) { handleMessage(event.data); });
          window.addEventListener('message', function(event) { handleMessage(event.data); });

          function handleMessage(dataStr) {
              try {
                  const message = JSON.parse(dataStr);
                  if (message.type === 'init_data') {
                      document.getElementById('status').innerText = 'Rendering PDF...';
                      loadPdf(message.data);
                  }
              } catch(e) { console.error(e); }
          }

          function loadPdf(data) {
              try {
                 const uint8Array = new Uint8Array(atob(data).split('').map(char => char.charCodeAt(0)));
                 pdfjsLib.getDocument({data: uint8Array}).promise.then(function(pdfDoc_) {
                    pdfDoc = pdfDoc_;
                    document.getElementById('loading').style.display = 'none';
                    for (let i = 1; i <= pdfDoc.numPages; i++) { renderPage(i); }
                 }).catch(function(reason) {
                    showError('Render Error: ' + reason.message);
                    window.ReactNativeWebView.postMessage(JSON.stringify({type: 'render_error', message: reason.message}));
                 });
              } catch(e) {
                  showError('Data Error: ' + e.message);
                  window.ReactNativeWebView.postMessage(JSON.stringify({type: 'render_error', message: e.message}));
              }
          }
      `;

  const pdfJsHtml = `
      <!DOCTYPE html>
      <html lang="en">
      <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
        <title>PDF Viewer</title>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
        <script>
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
        </script>
        <style>
          body { margin: 0; padding: 0; background-color: #525659; font-family: sans-serif; }
          #container { display: flex; flex-direction: column; align-items: center; padding: 10px 0; }
          .pdf-page { margin-bottom: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); background: white; }
          .message-box { color: white; padding: 20px; text-align: center; margin-top: 50px; }
          #status { font-size: 14px; opacity: 0.8; margin-bottom: 10px; }
        </style>
      </head>
      <body>
        <div id="loading" class="message-box">
            <div id="status">Preparing Document...</div>
        </div>
        <div id="container"></div>
        <script>
          let pdfDoc = null;
          const scale = 2.0;
          function renderPage(num) {
            pdfDoc.getPage(num).then(function(page) {
              const viewport = page.getViewport({scale: scale});
              const container = document.getElementById('container');
              const canvas = document.createElement('canvas');
              canvas.className = 'pdf-page';
              container.appendChild(canvas);
              const ctx = canvas.getContext('2d');
              canvas.height = viewport.height;
              canvas.width = viewport.width;
              canvas.style.width = '95%'; 
              canvas.style.height = 'auto';
              page.render({ canvasContext: ctx, viewport: viewport });
            });
          }
          function showError(msg) {
              document.getElementById('loading').innerHTML = '<div style="color:#ff6b6b; font-weight:bold;">' + msg + '</div>';
          }
          ${loaderScript}
        </script>
      </body>
      </html>
    `;

  const handleDownload = async () => {
    downloadFile(url, title, setDownloading);
  };

  const handleOpenInBrowser = () => {
    Linking.openURL(url).catch(() => Alert.alert('Error', 'Could not open URL in browser.'));
  };

  return (
    <View style={styles.container}>
      <StatusBar barStyle="dark-content" backgroundColor="#fff" />
      <View style={styles.header}>
        <TouchableOpacity onPress={() => navigation.goBack()} style={styles.backButton}>
          <Text style={styles.backButtonText}>←</Text>
        </TouchableOpacity>
        <Text style={styles.headerTitle} numberOfLines={1}>{title || 'Document'}</Text>
        <TouchableOpacity onPress={handleDownload} style={styles.downloadButton} disabled={downloading}>
          {downloading ? (
            <ActivityIndicator size="small" color="#4f46e5" />
          ) : (
            <Ionicons name={isLocalFile ? "share-outline" : "download-outline"} size={24} color="#4f46e5" />
          )}
        </TouchableOpacity>
      </View>

      <View style={styles.contentContainer}>
        {loadingFile ? (
          <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
            <ActivityIndicator size="large" color="#4f46e5" />
            <Text style={{ marginTop: 16, color: '#64748b', fontSize: 14 }}>Loading PDF...</Text>
          </View>
        ) : error ? (
          <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', padding: 24 }}>
            <Ionicons name="document-text-outline" size={64} color="#e2e8f0" />
            <Text style={{ fontSize: 18, fontWeight: 'bold', marginTop: 16, color: '#1e293b', textAlign: 'center' }}>
              Could Not Open PDF
            </Text>
            <Text style={{ color: '#64748b', textAlign: 'center', marginTop: 8, fontSize: 13, lineHeight: 20 }}>
              {error}
            </Text>
            <TouchableOpacity
              onPress={loadPdfData}
              style={{ marginTop: 20, backgroundColor: '#4f46e5', paddingHorizontal: 24, paddingVertical: 12, borderRadius: 10 }}
            >
              <Text style={{ color: 'white', fontWeight: 'bold' }}>🔄 Retry</Text>
            </TouchableOpacity>
            {!isLocalFile && (
              <TouchableOpacity
                onPress={handleOpenInBrowser}
                style={{ marginTop: 12, backgroundColor: '#0ea5e9', paddingHorizontal: 24, paddingVertical: 12, borderRadius: 10 }}
              >
                <Text style={{ color: 'white', fontWeight: 'bold' }}>🌐 Open in Browser</Text>
              </TouchableOpacity>
            )}
          </View>
        ) : (
          <WebView
            ref={webViewRef}
            originWhitelist={['*']}
            source={useGoogleDocs ? { uri: `https://docs.google.com/gview?embedded=true&url=${encodeURIComponent(url)}` } : { html: pdfJsHtml }}
            style={{ flex: 1, backgroundColor: '#525659' }}
            startInLoadingState={true}
            renderLoading={() => <ActivityIndicator size="large" color="#4f46e5" style={{ position: 'absolute', top: '50%', left: '50%' }} />}
            javaScriptEnabled={true}
            domStorageEnabled={true}
            scalesPageToFit={true}
            setSupportZoom={true}
            allowFileAccess={true}
            allowFileAccessFromFileURLs={true}
            allowUniversalAccessFromFileURLs={true}
            onLoadEnd={handleWebViewLoad}
            onMessage={(event) => {
              try {
                const msg = JSON.parse(event.nativeEvent.data || '{}');
                console.log('PDF Msg:', msg);
                if (msg.type === 'render_error' || msg.type === 'error') {
                  setError(
                    `PDF rendering failed: ${msg.message}\n\nThe file may be corrupted or in an unsupported format.\n\nURL: ${url}`
                  );
                }
              } catch(e) {}
            }}
          />
        )}
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
  contentContainer: { flex: 1 },
});

export default PDFViewerScreen;