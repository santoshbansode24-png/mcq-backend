/**
 * Streaming Utility for React Native
 * Handles Server-Sent Events (SSE) using XMLHttpRequest for maximum compatibility.
 */

export const streamFetch = async (url, options, onChunk, onDone, onError) => {
    try {
        const xhr = new XMLHttpRequest();
        xhr.open(options.method || 'GET', url, true);
        
        // ⚠️ DO NOT manually set Content-Type for FormData uploads.
        // XHR automatically sets multipart/form-data + the required boundary.
        // Setting it manually breaks the boundary and destroys file uploads.
        if (options.headers) {
            Object.keys(options.headers).forEach(key => {
                xhr.setRequestHeader(key, options.headers[key]);
            });
        }

        let lastIndex = 0;

        xhr.onreadystatechange = () => {
            if (xhr.readyState === 3 || xhr.readyState === 4) {
                const newText = xhr.responseText.substring(lastIndex);
                lastIndex = xhr.responseText.length;

                const lines = newText.split('\n');
                lines.forEach(line => {
                    if (line.startsWith('data: ')) {
                        const dataStr = line.substring(6).trim();
                        if (dataStr === '[DONE]') {
                            if (onDone) onDone();
                        } else {
                            try {
                                const json = JSON.parse(dataStr);
                                // Surface API-level errors (e.g., "No image uploaded", rate limits)
                                if (json.status === 'error' && onError) {
                                    onError(new Error(json.message || 'Server returned an error'));
                                } else {
                                    if (onChunk) onChunk(json);
                                }
                            } catch (e) {
                                // Ignore partial/invalid JSON in chunks
                            }
                        }
                    }
                });
            }

            if (xhr.readyState === 4) {
                if (xhr.status !== 200) {
                    if (onError) onError(new Error(`Server Error: ${xhr.status}`));
                } else {
                    if (onDone) onDone();
                }
            }
        };

        xhr.onerror = (e) => {
            if (onError) onError(new Error('Network Error'));
        };

        xhr.send(options.body || null);

    } catch (e) {
        if (onError) onError(e);
    }
};

