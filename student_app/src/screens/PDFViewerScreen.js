import React from 'react';
import { View, StyleSheet, TouchableOpacity, Text, StatusBar, ActivityIndicator, Platform, Alert } from 'react-native';
import { WebView } from 'react-native-webview';

import { downloadFile } from '../utils/downloadUtils';
import { Ionicons } from '@expo/vector-icons';

const PDFViewerScreen = ({ navigation, route }) => {
  const { url, title } = route.params || {};
  const [downloading, setDownloading] = React.useState(false);

  // Helper to determine if we can use Google Viewer (Public URLs) or need PDF.js (Localhost/Private)
  const isGoogleDrive = url?.includes('drive.google.com');

  // PDF.js Viewer HTML (Vertical Scrolling)
  const pdfJsHtml = `
      <!DOCTYPE html>
      <html lang="en">
      <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>PDF Viewer</title>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
        <script>
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
        </script>
        <style>
          body { margin: 0; padding: 0; background-color: #525659; }
          #container { display: flex; flex-direction: column; align-items: center; padding: 10px 0; }
          .pdf-page { margin-bottom: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); background: white; }
        </style>
      </head>
      <body>
        <div id="container"></div>

        <script>
          const url = '${url}';
          let pdfDoc = null;
          const scale = 1.5;

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

              // Fit to screen width logic
              canvas.style.width = '95%'; // slightly less than 100 to show background gap
              canvas.style.height = 'auto';

              const renderContext = {
                canvasContext: ctx,
                viewport: viewport
              };
              page.render(renderContext);
            });
          }

          pdfjsLib.getDocument(url).promise.then(function(pdfDoc_) {
            pdfDoc = pdfDoc_;
            // Render all pages
            for (let i = 1; i <= pdfDoc.numPages; i++) {
                renderPage(i);
            }
          }, function (reason) {
            console.error(reason);
            window.ReactNativeWebView.postMessage(JSON.stringify({type: 'error', message: reason.message}));
          });
        </script>
      </body>
      </html>
    `;

  const getSource = () => {
    if (isGoogleDrive) {
      // Use Google Viewer for Drive links
      return { uri: `https://docs.google.com/gview?embedded=true&url=${encodeURIComponent(url)}` };
    }
    // Use PDF.js for Direct/Local links
    return { html: pdfJsHtml };
  };

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
            <Ionicons name="download-outline" size={24} color="#4f46e5" />
          )}
        </TouchableOpacity>
      </View>

      <View style={styles.contentContainer}>
        <WebView
          originWhitelist={['*']}
          source={getSource()}
          style={{ flex: 1, backgroundColor: '#525659' }}
          startInLoadingState={true}
          renderLoading={() => <ActivityIndicator size="large" color="#4f46e5" style={{ position: 'absolute', top: '50%', left: '50%' }} />}
          javaScriptEnabled={true}
          domStorageEnabled={true}
          allowFileAccess={true}
          allowFileAccessFromFileURLs={true}
          allowUniversalAccessFromFileURLs={true}
          onMessage={(event) => {
            console.log('PDF Error:', event.nativeEvent.data);
            Alert.alert('Error', 'Failed to render PDF. Try downloading it.');
          }}
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
  contentContainer: { flex: 1 },
});

export default PDFViewerScreen;