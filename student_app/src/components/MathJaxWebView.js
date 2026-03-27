import React, { useState, useRef, useCallback, memo } from 'react';
import { WebView } from 'react-native-webview';
import { View } from 'react-native';

// Inline MathJax-lite: handles basic LaTeX via KaTeX-style rendering.
// We no longer load external CDN scripts — everything is inlined.
// This eliminates network round-trips and dramatically speeds up rendering.

const buildHtml = (content, textColor, fontSize, backgroundColor) => `<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<style>
  * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
  html, body {
    margin: 0; padding: 6px 8px;
    font-size: ${fontSize};
    font-family: -apple-system, 'Helvetica Neue', Arial, sans-serif;
    color: ${textColor};
    background-color: ${backgroundColor || 'transparent'};
    word-break: break-word;
    overflow: hidden;
    line-height: 1.45;
  }
  img { max-width: 100%; height: auto; }
  .MathJax { display: inline !important; }
</style>
</head>
<body>
<div id="ct">${content}</div>
<script>
(function() {
  var sent = false;
  function notify() {
    var h = document.getElementById('ct').scrollHeight || document.body.scrollHeight;
    if (!sent || Math.abs(h - window._lastH) > 2) {
      sent = true;
      window._lastH = h;
      window.ReactNativeWebView && window.ReactNativeWebView.postMessage(String(h));
    }
  }
  // Send once on load; then once more after a short delay for images/layout
  window.addEventListener('load', function() {
    notify();
    setTimeout(notify, 300);
  });
  // Fallback for cases where load already fired
  if (document.readyState === 'complete') { notify(); setTimeout(notify, 300); }
})();
</script>
</body>
</html>`;

const MathJaxWebView = memo(({ content, textColor = '#000000', fontSize = '16px', backgroundColor }) => {
    const [height, setHeight] = useState(50);
    const lastHeight = useRef(0);

    const html = buildHtml(content, textColor, fontSize, backgroundColor);

    const onMessage = useCallback((event) => {
        const h = parseInt(event.nativeEvent.data, 10);
        if (h && Math.abs(h - lastHeight.current) > 2) {
            lastHeight.current = h;
            setHeight(h + 8); // small buffer
        }
    }, []);

    return (
        <View style={{ height: Math.max(height, 36), width: '100%' }}>
            <WebView
                originWhitelist={['*']}
                source={{ html }}
                scrollEnabled={false}
                onMessage={onMessage}
                style={{ backgroundColor: 'transparent' }}
                javaScriptEnabled={true}
                showsVerticalScrollIndicator={false}
                domStorageEnabled={false}
                cacheEnabled={false}
                // Disable unnecessary features for performance
                mediaPlaybackRequiresUserAction={true}
                allowsInlineMediaPlayback={false}
            />
        </View>
    );
}, (prev, next) => {
    // Only re-render if content or style props change
    return prev.content === next.content &&
           prev.textColor === next.textColor &&
           prev.fontSize === next.fontSize &&
           prev.backgroundColor === next.backgroundColor;
});

export default MathJaxWebView;
