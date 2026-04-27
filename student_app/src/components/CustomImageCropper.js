import React, { useState, useRef } from 'react';
import {
    View, Image, StyleSheet, Dimensions,
    PanResponder, TouchableOpacity, Text, Modal, ActivityIndicator
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as ImageManipulator from 'expo-image-manipulator';

const CustomImageCropper = ({ visible, imageUri, onCropComplete, onCancel }) => {
    const [layout, setLayout] = useState(null);
    const [cropBox, setCropBox] = useState({ x: 50, y: 100, width: 220, height: 220 });
    const [processing, setProcessing] = useState(false);

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
        const L = layoutRef.current;
        if (!L) return box;
        let { x, y, width, height } = box;
        if (width < 60) width = 60;
        if (height < 60) height = 60;
        if (x < 0) x = 0;
        if (y < 0) y = 0;
        if (x + width > L.width)  { x = L.width - width;  if (x < 0) { x = 0; width = L.width; } }
        if (y + height > L.height){ y = L.height - height; if (y < 0) { y = 0; height = L.height; } }
        return { x, y, width, height };
    };

    const onImageLayout = (e) => {
        const { width, height } = e.nativeEvent.layout;
        layoutRef.current = { width, height };
        setLayout({ width, height });
        const box = constrain({ x: (width - 220) / 2, y: (height - 220) / 2, width: 220, height: 220 });
        updateBox(box);
    };

    // ─── DRAG (move whole box) ──────────────────────────────────────────────────
    const dragResponder = useRef(
        PanResponder.create({
            onStartShouldSetPanResponder: () => true,
            onPanResponderGrant: () => {
                // Snapshot where the box is right now
                gestureStartBox.current = { ...cropBoxRef.current };
            },
            onPanResponderMove: (_, { dx, dy }) => {
                const s = gestureStartBox.current;
                if (!s) return;
                // Apply total displacement against the snapshot — never against prev
                updateBox(constrain({ ...s, x: s.x + dx, y: s.y + dy }));
            },
            onPanResponderRelease: () => { gestureStartBox.current = null; },
            onPanResponderTerminate: () => { gestureStartBox.current = null; },
        })
    ).current;

    // ─── CORNER RESIZE ──────────────────────────────────────────────────────────
    const makeCornerResponder = (corner) => PanResponder.create({
        onStartShouldSetPanResponder: () => true,
        onPanResponderGrant: () => {
            gestureStartBox.current = { ...cropBoxRef.current };
        },
        onPanResponderMove: (_, { dx, dy }) => {
            const s = gestureStartBox.current;
            if (!s) return;
            let newBox = { ...s };
            // Each corner modifies x/y/w/h relative to snapshot
            if (corner === 'topLeft') {
                newBox.x = s.x + dx;
                newBox.y = s.y + dy;
                newBox.width  = s.width  - dx;
                newBox.height = s.height - dy;
            } else if (corner === 'topRight') {
                newBox.y      = s.y + dy;
                newBox.width  = s.width  + dx;
                newBox.height = s.height - dy;
            } else if (corner === 'bottomLeft') {
                newBox.x      = s.x + dx;
                newBox.width  = s.width  - dx;
                newBox.height = s.height + dy;
            } else { // bottomRight
                newBox.width  = s.width  + dx;
                newBox.height = s.height + dy;
            }
            updateBox(constrain(newBox));
        },
        onPanResponderRelease: () => { gestureStartBox.current = null; },
        onPanResponderTerminate: () => { gestureStartBox.current = null; },
    });

    const tlR = useRef(makeCornerResponder('topLeft')).current;
    const trR = useRef(makeCornerResponder('topRight')).current;
    const blR = useRef(makeCornerResponder('bottomLeft')).current;
    const brR = useRef(makeCornerResponder('bottomRight')).current;

    // ─── CROP ───────────────────────────────────────────────────────────────────
    const handleCrop = async () => {
        if (!layoutRef.current || !imageUri) return;
        setProcessing(true);

        try {
            // Get actual dimensions reliably using ImageManipulator
            const { width: actualWidth, height: actualHeight } = await ImageManipulator.manipulateAsync(imageUri, []);
            
            const L = layoutRef.current;
            const cb = cropBoxRef.current;
            
            // Calculate rendered dimensions inside the "contain" view
            const imageRatio = actualWidth / actualHeight;
            const layoutRatio = L.width / L.height;
            
            let renderedWidth, renderedHeight, offsetX, offsetY;

            if (imageRatio > layoutRatio) {
                // Image fills width, black bars on top/bottom
                renderedWidth = L.width;
                renderedHeight = L.width / imageRatio;
                offsetX = 0;
                offsetY = (L.height - renderedHeight) / 2;
            } else {
                // Image fills height, black bars on left/right
                renderedHeight = L.height;
                renderedWidth = L.height * imageRatio;
                offsetX = (L.width - renderedWidth) / 2;
                offsetY = 0;
            }

            // Calculate scale from rendered pixels to actual image pixels
            const scale = actualWidth / renderedWidth;

            // Map crop box coordinates to actual image coordinates
            // Use Math.round to avoid floating point issues with ImageManipulator
            let originX = Math.round((cb.x - offsetX) * scale);
            let originY = Math.round((cb.y - offsetY) * scale);
            let width = Math.round(cb.width * scale);
            let height = Math.round(cb.height * scale);

            // Clamp and ensure valid values
            if (originX < 0) {
                width += originX; // reduce width
                originX = 0;
            }
            if (originY < 0) {
                height += originY; // reduce height
                originY = 0;
            }

            // Ensure width/height don't exceed image bounds
            if (originX + width > actualWidth) width = actualWidth - originX;
            if (originY + height > actualHeight) height = actualHeight - originY;

            // Final safety check - if box is outside or zero
            if (width <= 0 || height <= 0) {
                originX = 0; originY = 0; width = actualWidth; height = actualHeight;
            }

            const cropAction = { originX, originY, width, height };

            const result = await ImageManipulator.manipulateAsync(
                imageUri,
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
                <View style={styles.cropContainer}>
                    <Image
                        source={{ uri: imageUri }}
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
        borderRadius: 4,
        zIndex: 30,
    },
    tl: { top: -CORNER / 2, left: -CORNER / 2 },
    tr: { top: -CORNER / 2, right: -CORNER / 2 },
    bl: { bottom: -CORNER / 2, left: -CORNER / 2 },
    br: { bottom: -CORNER / 2, right: -CORNER / 2 },
    footer: { paddingVertical: 22, backgroundColor: '#111', alignItems: 'center' },
    footerText: { color: '#FFD700', fontSize: 14, fontWeight: '600' },
});

export default CustomImageCropper;
