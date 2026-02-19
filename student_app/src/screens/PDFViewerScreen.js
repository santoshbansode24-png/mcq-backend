import React from 'react';
import { View, StyleSheet, TouchableOpacity, Text, StatusBar, ActivityIndicator, Platform, Alert } from 'react-native';
import { WebView } from 'react-native-webview';
import * as FileSystem from 'expo-file-system/legacy'; // Use legacy to match downloadUtils

import { downloadFile } from '../utils/downloadUtils';
import { Ionicons } from '@expo/vector-icons';

const PDFViewerScreen = ({ navigation, route }) => {
  const { url, title } = route.params || {};
  const [downloading, setDownloading] = React.useState(false);
  const [loadingFile, setLoadingFile] = React.useState(true);
  const [pdfBase64, setPdfBase64] = React.useState(null);
  const [error, setError] = React.useState(null);
  const [webViewLoaded, setWebViewLoaded] = React.useState(false);
  const webViewRef = React.useRef(null);

  // ROBUST CHECK: If it looks like a file, treat it as local.
  // Defaults to TRUE if not http/https to avoid accidental remote fetch of local paths.
  const isLocalFile = url && (url.startsWith('file:') || url.startsWith('/') || (!url.startsWith('http') && !url.startsWith('https')));

  React.useEffect(() => {
    console.log("PDFViewer Debug - URL:", url, "isLocal:", isLocalFile);

    const loadPdfData = async () => {
      // Only load base64 if it's a local file
      if (isLocalFile) {
        try {
          console.log("Reading file as Base64...");
          const base64 = await FileSystem.readAsStringAsync(url, { encoding: 'base64' });
          console.log("Base64 read success, length:", base64.length);
          setPdfBase64(base64);
        } catch (error) {
          console.error("Error reading PDF file:", error);
          setError("Could not read file: " + error.message);
        }
      }
      setLoadingFile(false);
    };

    if (url) {
      loadPdfData();
    } else {
      setError("No URL provided to viewer");
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

  // CONDITIONAL SCRIPT GENERATION
  // We determine strictly in JS which script to inject, so the HTML assumes nothing.
  let loaderScript = '';

  if (isLocalFile) {
    // SCRIPT FOR LOCAL FILES (Wait for Message)
    loaderScript = `
          // Listen for Base64 data from React Native
          document.addEventListener('message', function(event) {
              handleMessage(event.data);
          });
          window.addEventListener('message', function(event) {
              handleMessage(event.data);
          });

          function handleMessage(dataStr) {
              try {
                  const message = JSON.parse(dataStr);
                  if (message.type === 'init_data') {
                      document.getElementById('status').innerText = 'Rendering PDF...';
                      loadPdf(message.data);
                  }
              } catch(e) {
                  console.error(e);
              }
          }

          function loadPdf(data) {
              try {
                 const uint8Array = new Uint8Array(atob(data).split('').map(char => char.charCodeAt(0)));
                 pdfjsLib.getDocument({data: uint8Array}).promise.then(function(pdfDoc_) {
                    pdfDoc = pdfDoc_;
                    document.getElementById('loading').style.display = 'none';
                    for (let i = 1; i <= pdfDoc.numPages; i++) {
                        renderPage(i);
                    }
                 }).catch(function(reason) {
                    showError('Render Error: ' + reason.message);
                 });
              } catch(e) {
                  showError('Data Error: ' + e.message);
              }
          }
      `;
  } else {
    // SCRIPT FOR REMOTE URLS (Load Directly)
    loaderScript = `
          document.getElementById('status').innerText = 'Loading Remote URL...';
          pdfjsLib.getDocument('${url}').promise.then(function(pdfDoc_) {
            pdfDoc = pdfDoc_;
            document.getElementById('loading').style.display = 'none';
            for (let i = 1; i <= pdfDoc.numPages; i++) {
                renderPage(i);
            }
          }).catch(function(reason){
             showError('Remote Fetch Error: ' + reason.message);
          });
      `;
  }

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
          const scale = 2.0; // Increased scale for better clarity

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
              const renderContext = { canvasContext: ctx, viewport: viewport };
              page.render(renderContext);
            });
          }

          function showError(msg) {
              const box = document.getElementById('loading');
              box.innerHTML = '<div style="color:#ff6b6b; font-weight:bold;">' + msg + '</div>';
              window.ReactNativeWebView.postMessage(JSON.stringify({type: 'error', message: msg}));
          }

          ${loaderScript}
        </script>
      </body>
      </html>
    `;

  const handleDownload = async () => {
    downloadFile(url, title, setDownloading);
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
        {error ? (
          <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
            <Text style={{ fontSize: 16, fontWeight: 'bold', marginBottom: 10 }}>Error</Text>
            <Text style={{ color: 'red', textAlign: 'center', padding: 20 }}>{error}</Text>
          </View>
        ) : (
          <WebView
            ref={webViewRef}
            originWhitelist={['*']}
            source={{ html: pdfJsHtml }}
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
              console.log('PDF Msg:', event.nativeEvent.data);
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