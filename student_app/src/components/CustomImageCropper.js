import React, { useState, useRef } from 'react';
import {
    View, Image, StyleSheet, Dimensions,
    PanResponder, TouchableOpacity, Text, Modal, ActivityIndicator
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as ImageManipulator from 'expo-image-manipulator';
import * as Haptics from 'expo-haptics';

const CustomImageCropper = ({ visible, imageUri, onCropComplete, onCancel }) => {
    const [workingUri, setWorkingUri] = useState(null);
    const [preparing, setPreparing] = useState(false);
    const [layout, setLayout] = useState(null);
    const [cropBox, setCropBox] = useState({ x: 50, y: 100, width: 220, height: 220 });
    const [processing, setProcessing] = useState(false);
    const actualDimensionsRef = useRef(null);
    const imageBoundsRef = useRef(null);

    React.useEffect(() => {
        if (visible && imageUri) {
            prepareImage(imageUri);
        } else {
            setWorkingUri(null);
            setLayout(null);
        }
    }, [visible, imageUri]);

    const prepareImage = async (uri) => {
        setPreparing(true);
        try {
            const { w, h } = await new Promise((resolve, reject) => {
                Image.getSize(uri, (width, height) => resolve({ w: width, h: height }), reject);
            });

            let actions = [];
            
            // FIX: Force a 0-degree rotation. This tells ImageManipulator to read the EXIF 
            // orientation (e.g. 'Rotate 90 CW' from a phone camera) and physically bake 
            // that rotation into the raw pixels, stripping the EXIF tag. 
            // This guarantees the visual layout exactly matches the pixel array.
            actions.push({ rotate: 0 });

            // Optimize memory by downscaling huge images before cropping (Max 2048px)
            if (w > 2048 || h > 2048) {
                if (w > h) actions.push({ resize: { width: 2048 } });
                else actions.push({ resize: { height: 2048 } });
            } else {
                // FORCE a resize even if small to guarantee EXIF is stripped and baked on all Android devices
                actions.push({ resize: { width: w } });
            }

            const result = await ImageManipulator.manipulateAsync(uri, actions, { compress: 1, format: ImageManipulator.SaveFormat.JPEG });
            actualDimensionsRef.current = { w: result.width, h: result.height };
            setWorkingUri(result.uri);
        } catch (e) {
            console.warn("Image prep failed, falling back", e);
            actualDimensionsRef.current = { w, h };
            setWorkingUri(uri);
        } finally {
            setPreparing(false);
        }
    };

    // Live refs — avoids stale closures inside PanResponder handlers
    const cropBoxRef = useRef(cropBox);
    const layoutRef = useRef(null);
    // Snapshot of cropBox at gesture start
    const gestureStartBox = useRef(null);

    const updateBox = (next) => {
        cropBoxRef.current = next;
        setCropBox(next);
    };

    const constrain = (box) => {
        const bounds = imageBoundsRef.current;
        if (!bounds) return box;
        let { x, y, width, height } = box;
        const MIN_SIZE = 60;
        if (width < MIN_SIZE) width = MIN_SIZE;
        if (height < MIN_SIZE) height = MIN_SIZE;
        if (x < bounds.x) x = bounds.x;
        if (y < bounds.y) y = bounds.y;
        if (x + width > bounds.x + bounds.width)  { x = bounds.x + bounds.width - width;  if (x < bounds.x) { x = bounds.x; width = bounds.width; } }
        if (y + height > bounds.y + bounds.height){ y = bounds.y + bounds.height - height; if (y < bounds.y) { y = bounds.y; height = bounds.height; } }
        return { x, y, width, height };
    };

    const onImageLayout = (e) => {
        const { width, height } = e.nativeEvent.layout;
        layoutRef.current = { width, height };
        setLayout({ width, height });

        let rWidth = width;
        let rHeight = height;
        let oX = 0;
        let oY = 0;

        if (actualDimensionsRef.current) {
            const actualWidth = actualDimensionsRef.current.w;
            const actualHeight = actualDimensionsRef.current.h;
            const imageRatio = actualWidth / actualHeight;
            const layoutRatio = width / height;

            if (imageRatio > layoutRatio) {
                rWidth = width;
                rHeight = width / imageRatio;
                oY = (height - rHeight) / 2;
            } else {
                rHeight = height;
                rWidth = height * imageRatio;
                oX = (width - rWidth) / 2;
            }
        }

        imageBoundsRef.current = { x: oX, y: oY, width: rWidth, height: rHeight };

        const w = rWidth * 0.8;
        const h = Math.min(rHeight * 0.5, w * 0.6); // Start with a landscape-ish box for text
        const box = constrain({ x: oX + (rWidth - w) / 2, y: oY + (rHeight - h) / 2, width: w, height: h });
        updateBox(box);
    };

    // ─── DRAG (move whole box) ──────────────────────────────────────────────────
    const dragResponder = useRef(
        PanResponder.create({
            onStartShouldSetPanResponder: () => true,
            onPanResponderGrant: () => {
                // Snapshot where the box is right now
                gestureStartBox.current = { ...cropBoxRef.current };
                Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
            },
            onPanResponderMove: (_, { dx, dy }) => {
                const s = gestureStartBox.current;
                if (!s) return;
                // Apply total displacement against the snapshot — never against prev
                updateBox(constrain({ ...s, x: s.x + dx, y: s.y + dy }));
            },
            onPanResponderRelease: () => { 
                gestureStartBox.current = null;
                Haptics.selectionAsync();
            },
            onPanResponderTerminate: () => { gestureStartBox.current = null; },
        })
    ).current;

    // ─── CORNER RESIZE ──────────────────────────────────────────────────────────
    const makeCornerResponder = (corner) => PanResponder.create({
        onStartShouldSetPanResponder: () => true,
        onPanResponderGrant: () => {
            gestureStartBox.current = { ...cropBoxRef.current };
            Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
        },
        onPanResponderMove: (_, { dx, dy }) => {
            const s = gestureStartBox.current;
            if (!s) return;
            
            let newX = s.x;
            let newY = s.y;
            let newW = s.width;
            let newH = s.height;
            const MIN_SIZE = 60;

            if (corner === 'topLeft') {
                newX = s.x + dx;
                newY = s.y + dy;
                newW = s.width - dx;
                newH = s.height - dy;
                
                // Enforce minimums while anchoring opposite (bottom/right) edges
                if (newW < MIN_SIZE) { newW = MIN_SIZE; newX = s.x + s.width - MIN_SIZE; }
                if (newH < MIN_SIZE) { newH = MIN_SIZE; newY = s.y + s.height - MIN_SIZE; }
            } else if (corner === 'topRight') {
                newY = s.y + dy;
                newW = s.width + dx;
                newH = s.height - dy;
                
                if (newW < MIN_SIZE) newW = MIN_SIZE; // Left edge anchored
                if (newH < MIN_SIZE) { newH = MIN_SIZE; newY = s.y + s.height - MIN_SIZE; } // Bottom edge anchored
            } else if (corner === 'bottomLeft') {
                newX = s.x + dx;
                newW = s.width - dx;
                newH = s.height + dy;
                
                if (newW < MIN_SIZE) { newW = MIN_SIZE; newX = s.x + s.width - MIN_SIZE; } // Right edge anchored
                if (newH < MIN_SIZE) newH = MIN_SIZE; // Top edge anchored
            } else { // bottomRight
                newW = s.width + dx;
                newH = s.height + dy;
                
                if (newW < MIN_SIZE) newW = MIN_SIZE; // Left edge anchored
                if (newH < MIN_SIZE) newH = MIN_SIZE; // Top edge anchored
            }

            // Now apply global bounds
            const bounds = imageBoundsRef.current;
            if (bounds) {
                if (newX < bounds.x) { newW -= (bounds.x - newX); newX = bounds.x; }
                if (newY < bounds.y) { newH -= (bounds.y - newY); newY = bounds.y; }
                if (newX + newW > bounds.x + bounds.width) newW = bounds.x + bounds.width - newX;
                if (newY + newH > bounds.y + bounds.height) newH = bounds.y + bounds.height - newY;
                
                // Final safety
                if (newW < MIN_SIZE) newW = MIN_SIZE;
                if (newH < MIN_SIZE) newH = MIN_SIZE;
            }

            updateBox({ x: newX, y: newY, width: newW, height: newH });
        },
        onPanResponderRelease: () => { 
            gestureStartBox.current = null;
            Haptics.selectionAsync();
        },
        onPanResponderTerminate: () => { gestureStartBox.current = null; },
    });

    const tlR = useRef(makeCornerResponder('topLeft')).current;
    const trR = useRef(makeCornerResponder('topRight')).current;
    const blR = useRef(makeCornerResponder('bottomLeft')).current;
    const brR = useRef(makeCornerResponder('bottomRight')).current;

    // ─── CROP ───────────────────────────────────────────────────────────────────
    const handleCrop = async () => {
        if (!layoutRef.current || !workingUri) return;
        setProcessing(true);

        try {
            // FIX: Use the true physical dimensions captured directly from ImageManipulator.
            // React Native Web's Image.getSize() returns logical pixels (divided by devicePixelRatio),
            // which completely ruins the crop bounding math on high-density screens.
            let actualWidth = actualDimensionsRef.current?.w;
            let actualHeight = actualDimensionsRef.current?.h;

            if (!actualWidth || !actualHeight) {
                const size = await new Promise((resolve, reject) => {
                    Image.getSize(workingUri, (w, h) => resolve({ actualWidth: w, actualHeight: h }), reject);
                });
                actualWidth = size.actualWidth;
                actualHeight = size.actualHeight;
            }
            
            const L = layoutRef.current;
            const cb = cropBoxRef.current;
            const bounds = imageBoundsRef.current;
            
            if (!bounds) return;

            // Calculate scale from rendered pixels to actual image pixels
            const scale = actualWidth / bounds.width;

            // Map crop box coordinates to actual image coordinates
            // Use Math.round to avoid floating point issues with ImageManipulator
            let originX = Math.round((cb.x - bounds.x) * scale);
            let originY = Math.round((cb.y - bounds.y) * scale);
            let cropWidth = Math.round(cb.width * scale);
            let cropHeight = Math.round(cb.height * scale);

            // Clamp and ensure valid values
            if (originX < 0) originX = 0;
            if (originY < 0) originY = 0;

            // Ensure width/height don't exceed image bounds
            if (originX + cropWidth > actualWidth) cropWidth = actualWidth - originX;
            if (originY + cropHeight > actualHeight) cropHeight = actualHeight - originY;

            // Final safety check - if box is outside or zero
            if (cropWidth <= 0 || cropHeight <= 0) {
                originX = 0; originY = 0; cropWidth = actualWidth; cropHeight = actualHeight;
            }

            const cropAction = { originX, originY, width: cropWidth, height: cropHeight };

            const result = await ImageManipulator.manipulateAsync(
                workingUri,
                [{ crop: cropAction }],
                { compress: 0.92, format: ImageManipulator.SaveFormat.JPEG }
            );
            
            setProcessing(false);
            onCropComplete(result.uri);
        } catch (err) {
            console.error('Crop failed:', err);
            setProcessing(false);
            // Fallback: just return the original image if crop fails
            onCropComplete(imageUri);
        }
    };

    if (!visible || !imageUri) return null;

    return (
        <Modal visible={visible} animationType="slide" transparent={false} statusBarTranslucent>
            <View style={styles.container}>
                {/* Header */}
                <View style={styles.header}>
                    <TouchableOpacity onPress={onCancel} style={styles.headerBtn}>
                        <Ionicons name="close" size={26} color="#fff" />
                        <Text style={styles.headerText}>Cancel</Text>
                    </TouchableOpacity>
                    <Text style={styles.headerTitle}>Crop Image</Text>
                    <TouchableOpacity onPress={handleCrop} style={styles.headerBtn} disabled={processing}>
                        {processing
                            ? <ActivityIndicator color="#00e5ff" />
                            : <Ionicons name="checkmark" size={26} color="#00e5ff" />}
                        <Text style={[styles.headerText, { color: '#00e5ff', fontWeight: 'bold' }]}>Done</Text>
                    </TouchableOpacity>
                </View>

                {/* Image + Crop Overlay */}
                <View style={styles.cropContainer} pointerEvents={processing ? 'none' : 'auto'}>
                    {preparing ? (
                        <View style={styles.loaderContainer}>
                            <ActivityIndicator size="large" color="#FFD700" />
                            <Text style={styles.loaderText}>Optimizing Image...</Text>
                        </View>
                    ) : workingUri ? (
                        <>
                            <Image
                                source={{ uri: workingUri }}
                                style={styles.image}
                                resizeMode="contain"
                                onLayout={onImageLayout}
                            />

                    {layout && (
                        <>
                            {/* Dark overlays */}
                            <View style={[styles.overlay, { top: 0, left: 0, right: 0, height: cropBox.y }]} />
                            <View style={[styles.overlay, { top: cropBox.y + cropBox.height, left: 0, right: 0, bottom: 0 }]} />
                            <View style={[styles.overlay, { top: cropBox.y, left: 0, width: cropBox.x, height: cropBox.height }]} />
                            <View style={[styles.overlay, { top: cropBox.y, left: cropBox.x + cropBox.width, right: 0, height: cropBox.height }]} />

                            {/* Crop box — draggable */}
                            <View
                                style={[styles.cropBox, { left: cropBox.x, top: cropBox.y, width: cropBox.width, height: cropBox.height }]}
                                {...dragResponder.panHandlers}
                            >
                                {/* Rule-of-thirds grid */}
                                <View style={styles.gridV1} /><View style={styles.gridV2} />
                                <View style={styles.gridH1} /><View style={styles.gridH2} />

                                {/* Corner handles */}
                                <View style={[styles.corner, styles.tl]} {...tlR.panHandlers} />
                                <View style={[styles.corner, styles.tr]} {...trR.panHandlers} />
                                <View style={[styles.corner, styles.bl]} {...blR.panHandlers} />
                                <View style={[styles.corner, styles.br]} {...brR.panHandlers} />
                            </View>
                            </>
                        )}
                    </>
                ) : null}
            </View>

                <View style={styles.footer}>
                    <Text style={styles.footerText}>Drag the box · Pull corners to resize</Text>
                </View>
            </View>
        </Modal>
    );
};

const CORNER = 28;

const styles = StyleSheet.create({
    container: { flex: 1, backgroundColor: '#0d0d0d' },
    header: {
        flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
        paddingHorizontal: 20, paddingTop: 52, paddingBottom: 16,
        backgroundColor: '#111',
    },
    headerBtn: { flexDirection: 'row', alignItems: 'center', gap: 6 },
    headerTitle: { color: '#fff', fontSize: 17, fontWeight: '700' },
    headerText: { color: '#ccc', fontSize: 15 },
    loaderContainer: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    loaderText: { color: '#FFD700', marginTop: 12, fontSize: 16, fontWeight: '600' },
    cropContainer: { flex: 1, backgroundColor: '#111', position: 'relative' },
    image: { width: '100%', height: '100%' },
    overlay: { position: 'absolute', backgroundColor: 'rgba(0,0,0,0.72)' },
    cropBox: {
        position: 'absolute',
        borderWidth: 2,
        borderColor: '#FFD700',
        backgroundColor: 'transparent',
        zIndex: 20,
    },
    // Rule-of-thirds lines
    gridV1: { position: 'absolute', left: '33.3%', width: 1, top: 0, bottom: 0, backgroundColor: 'rgba(255,215,0,0.35)' },
    gridV2: { position: 'absolute', left: '66.6%', width: 1, top: 0, bottom: 0, backgroundColor: 'rgba(255,215,0,0.35)' },
    gridH1: { position: 'absolute', top: '33.3%', height: 1, left: 0, right: 0, backgroundColor: 'rgba(255,215,0,0.35)' },
    gridH2: { position: 'absolute', top: '66.6%', height: 1, left: 0, right: 0, backgroundColor: 'rgba(255,215,0,0.35)' },
    // Corner handles
    corner: {
        position: 'absolute',
        width: CORNER, height: CORNER,
        backgroundColor: '#FFD700',
        borderRadius: CORNER / 2,
        zIndex: 30,
        borderWidth: 2,
        borderColor: '#fff',
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 2 },
        shadowOpacity: 0.3,
        shadowRadius: 3,
        elevation: 5,
    },
    tl: { top: -CORNER / 3, left: -CORNER / 3 },
    tr: { top: -CORNER / 3, right: -CORNER / 3 },
    bl: { bottom: -CORNER / 3, left: -CORNER / 3 },
    br: { bottom: -CORNER / 3, right: -CORNER / 3 },
    footer: { paddingVertical: 22, backgroundColor: '#111', alignItems: 'center' },
    footerText: { color: '#FFD700', fontSize: 14, fontWeight: '600' },
});

export default CustomImageCropper;
