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
        let buffer = '';
        let finished = false;

        const safeOnDone = () => {
            if (!finished) {
                finished = true;
                if (onDone) onDone();
            }
        };

        const safeOnError = (err) => {
            if (!finished) {
                finished = true;
                if (onError) onError(err);
            }
        };

        xhr.onreadystatechange = () => {
            if (xhr.readyState === 3 || xhr.readyState === 4) {
                const newText = xhr.responseText.substring(lastIndex);
                lastIndex = xhr.responseText.length;

                buffer += newText;
                const lines = buffer.split('\n');
                
                // The last element is either an incomplete line or an empty string.
                // Pop it off and hold it in the buffer for the next chunk.
                buffer = lines.pop();

                lines.forEach(line => {
                    if (line.startsWith('data: ')) {
                        const dataStr = line.substring(6).trim();
                        if (dataStr === '[DONE]') {
                            safeOnDone();
                        } else {
                            try {
                                const json = JSON.parse(dataStr);
                                // Surface API-level errors (e.g., "No image uploaded", rate limits)
                                if (json.status === 'error') {
                                    safeOnError(new Error(json.message || 'Server returned an error'));
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
                    safeOnError(new Error(`Server Error: ${xhr.status}`));
                } else {
                    safeOnDone();
                }
            }
        };

        xhr.onerror = (e) => {
            safeOnError(new Error('Network Error'));
        };

        xhr.send(options.body || null);

    } catch (e) {
        if (onError) onError(e);
    }
};

