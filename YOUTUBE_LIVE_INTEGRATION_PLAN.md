# Veeru App - Native YouTube Live Integration Plan

## Objective
Enable a "One-Click" Live Class experience. Teachers click a single button to start broadcasting from their phone camera. The video is automatically streamed to a central YouTube account in the background, embedded in the Student App for live viewing, and saved permanently for free replays.

## Technical Architecture

### 1. Backend (PHP & Google Cloud)
- **YouTube Data API v3 Integration:** Create endpoints to authenticate a central Google Account using an OAuth 2.0 refresh token.
- **`create_live_stream.php`:**
  - Call `liveBroadcasts.insert` to create the broadcast event.
  - Call `liveStreams.insert` to create the video ingest stream.
  - Call `liveBroadcasts.bind` to link the stream to the broadcast.
  - **Returns:** YouTube `Video ID` (for students to watch) and `RTMP URL + Stream Key` (for the teacher's phone to broadcast to).
- **`end_live_stream.php`:**
  - Call `liveBroadcasts.transition` to cleanly end the stream and trigger YouTube's automatic archiving.

### 2. Teacher App (Native Android)
- **Transition from PWA to Native:** The Teacher App will be compiled into a standalone `.apk` using Expo Prebuild / EAS Build (this allows us to use native camera broadcasting code that web browsers block).
- **RTMP Broadcasting Engine:** Install a powerful native video library (such as `react-native-rtmp-publisher` or `react-native-nodemediaclient`).
- **UI Flow:**
  1. Teacher taps "Start Live Class" and types a title.
  2. App calls `create_live_stream.php` and instantly receives the RTMP credentials.
  3. A full-screen camera view opens and automatically connects to the YouTube RTMP URL.
  4. The teacher taps "End Class" to stop the camera and notify the server to archive the video.

### 3. Student App (Native Android)
- Uses `react-native-youtube-iframe` (already implemented).
- Automatically receives the `Video ID` via push notification when the teacher goes live.
- Plays the live stream perfectly. When the class is over, the exact same screen will play the recorded video for revision.

## Action Plan for Tomorrow
1. **Google Cloud Setup:** We will create a GCP Project, enable the YouTube Data API, and generate the required OAuth refresh tokens.
2. **PHP Backend Development:** We will write the scripts that automate the creation of YouTube live streams.
3. **Teacher App Native Setup:** We will configure your Teacher Expo project to support custom native modules so it can broadcast video.
4. **RTMP Camera Integration:** We will build the live camera broadcasting screen in the Teacher App.
5. **End-to-End Testing:** We will do a full test run from Teacher Camera -> YouTube -> Student Screen!
