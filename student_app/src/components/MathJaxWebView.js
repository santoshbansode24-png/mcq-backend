import React, { useState, useEffect } from 'react';
import { WebView } from 'react-native-webview';
import { View, ActivityIndicator, StyleSheet } from 'react-native';

const MathJaxWebView = ({ content, textColor = '#000000', fontSize = '18px' }) => {
    // Default height, will update via onMessage
    const [height, setHeight] = useState(100);

    const htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes" />
            <script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
            <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
            <style>
                body {
                    font-size: ${fontSize};
                    font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                    color: ${textColor};
                    margin: 0;
                    padding: 10px;
                    background-color: transparent;
                    word-wrap: break-word;
                }
                /* Hide the MathJax loading message */
                #MathJax_Message { display: none !important; }
            </style>
        </head>
        <body>
            <div id="content">${content}</div>
            <script>
                // Post height to RN
                function sendHeight() {
                    const height = document.body.scrollHeight;
                    window.ReactNativeWebView.postMessage(height.toString());
                }
                
                // Send height on load and resize
                window.onload = sendHeight;
                // Check periodically nicely because MathJax renders async
                setInterval(sendHeight, 500);
            </script>
        </body>
        </html>
    `;

    return (
        <View style={{ height: height, minHeight: 60, width: '100%' }}>
            <WebView
                originWhitelist={['*']}
                source={{ html: htmlContent }}
                scrollEnabled={false}
                onMessage={(event) => {
                    const newHeight = parseInt(event.nativeEvent.data);
                    if (newHeight && newHeight !== height) {
                        setHeight(newHeight + 20); // Add buffer
                    }
                }}
                style={{ backgroundColor: 'transparent' }}
                javaScriptEnabled={true}
                showsVerticalScrollIndicator={false}
            />
        </View>
    );
};

export default MathJaxWebView;
