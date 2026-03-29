import React, { useState, useRef } from 'react';
import { View, Image, StyleSheet, Dimensions, PanResponder, TouchableOpacity, Text, Modal, ActivityIndicator } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as ImageManipulator from 'expo-image-manipulator';

const { width: SCREEN_WIDTH, height: SCREEN_HEIGHT } = Dimensions.get('window');

const CustomImageCropper = ({ visible, imageUri, onCropComplete, onCancel }) => {
    const [layout, setLayout] = useState(null);
    const [cropBox, setCropBox] = useState({ x: 50, y: 100, width: 200, height: 200 });
    const [processing, setProcessing] = useState(false);

    // Keep a live ref to cropBox so PanResponders can read it without stale closures
    const cropBoxRef = useRef(cropBox);
    const layoutRef = useRef(layout);

    // Initial Image Layout
    const onImageLayout = (event) => {
        const { width, height } = event.nativeEvent.layout;
        const newLayout = { width, height };
        setLayout(newLayout);
        layoutRef.current = newLayout;
        const newBox = {
            x: (width - 250) / 2,
            y: (height - 250) / 2,
            width: 250,
            height: 250
        };
        setCropBox(newBox);
        cropBoxRef.current = newBox;
    };

    // Calculate Limits
    const getConstrainedBox = (newBox) => {
        const currentLayout = layoutRef.current;
        if (!currentLayout) return newBox;
        let { x, y, width, height } = newBox;
        if (width < 50) width = 50;
        if (height < 50) height = 50;
        if (x < 0) x = 0;
        if (y < 0) y = 0;
        if (x + width > currentLayout.width) x = currentLayout.width - width;
        if (y + height > currentLayout.height) y = currentLayout.height - height;
        if (x < 0) width += x;
        return { x, y, width, height };
    };

    // Pan Responder for Dragging the Whole Box — created once
    const panResponder = useRef(
        PanResponder.create({
            onStartShouldSetPanResponder: () => true,
            onPanResponderMove: (_, gestureState) => {
                setCropBox(prev => {
                    const next = getConstrainedBox({
                        ...prev,
                        x: prev.x + gestureState.dx,
                        y: prev.y + gestureState.dy
                    });
                    cropBoxRef.current = next;
                    return next;
                });
            },
        })
    ).current;

    // Corner Resizing — created once per corner with useRef
    const makeCornerResponder = (corner) => PanResponder.create({
        onStartShouldSetPanResponder: () => true,
        onPanResponderMove: (_, gestureState) => {
            setCropBox(prev => {
                let newBox = { ...prev };
                const { dx, dy } = gestureState;
                if (corner === 'topLeft') {
                    newBox.x += dx; newBox.y += dy;
                    newBox.width -= dx; newBox.height -= dy;
                } else if (corner === 'topRight') {
                    newBox.y += dy; newBox.width += dx; newBox.height -= dy;
                } else if (corner === 'bottomLeft') {
                    newBox.x += dx; newBox.width -= dx; newBox.height += dy;
                } else if (corner === 'bottomRight') {
                    newBox.width += dx; newBox.height += dy;
                }
                const next = getConstrainedBox(newBox);
                cropBoxRef.current = next;
                return next;
            });
        }
    });

    const tlResponder = useRef(makeCornerResponder('topLeft')).current;
    const trResponder = useRef(makeCornerResponder('topRight')).current;
    const blResponder = useRef(makeCornerResponder('bottomLeft')).current;
    const brResponder = useRef(makeCornerResponder('bottomRight')).current;

    const handleCrop = async () => {
        if (!layout || !imageUri) return;
        setProcessing(true);

        try {
            // Calculate scale between displayed image and actual image
            // We need to know actual image dimensions. 
            // Image.getSize fetches actual dimensions.
            Image.getSize(imageUri, async (actualWidth, actualHeight) => {
                const scaleX = actualWidth / layout.width;
                const scaleY = actualHeight / layout.height;

                const cropAction = {
                    originX: cropBox.x * scaleX,
                    originY: cropBox.y * scaleY,
                    width: cropBox.width * scaleX,
                    height: cropBox.height * scaleY
                };

                if (cropAction.originX < 0) cropAction.originX = 0;
                if (cropAction.originY < 0) cropAction.originY = 0;  // ← was: originY = 0 (typo!)

                // Ensure we don't crop outside bounds due to floating point rounding
                if (cropAction.originX + cropAction.width > actualWidth) cropAction.width = actualWidth - cropAction.originX;
                if (cropAction.originY + cropAction.height > actualHeight) cropAction.height = actualHeight - cropAction.originY;

                const manipulated = await ImageManipulator.manipulateAsync(
                    imageUri,
                    [{ crop: cropAction }],
                    { compress: 1, format: ImageManipulator.SaveFormat.JPEG }
                );

                setProcessing(false);
                onCropComplete(manipulated.uri);
            }, (error) => {
                console.error("Failed to get image size", error);
                setProcessing(false);
            });

        } catch (error) {
            console.error("Crop failed", error);
            setProcessing(false);
        }
    };

    if (!visible || !imageUri) return null;

    return (
        <Modal visible={visible} animationType="slide" transparent={false}>
            <View style={styles.container}>
                {/* Header Actions */}
                <View style={styles.header}>
                    <TouchableOpacity onPress={onCancel} style={styles.headerBtn}>
                        <Ionicons name="close" size={28} color="#fff" />
                        <Text style={styles.headerText}>Cancel</Text>
                    </TouchableOpacity>
                    <TouchableOpacity onPress={handleCrop} style={styles.headerBtn}>
                        {processing ? <ActivityIndicator color="#00e5ff" /> : <Ionicons name="checkmark" size={28} color="#00e5ff" />}
                        <Text style={[styles.headerText, { color: '#00e5ff', fontWeight: 'bold' }]}>Done</Text>
                    </TouchableOpacity>
                </View>

                {/* Cropping Area */}
                <View style={styles.cropContainer}>
                    <Image
                        source={{ uri: imageUri }}
                        style={styles.image}
                        resizeMode="contain"
                        onLayout={onImageLayout}
                    />

                    {/* Overlay - Darkened Areas outside crop box */}
                    {layout && (
                        <>
                            {/* Using a simplified absolute overlay approach for high visibility border */}
                            {/* The Crop Box Itself - Draggable */}
                            <View
                                style={[
                                    styles.cropBox,
                                    {
                                        left: cropBox.x,
                                        top: cropBox.y,
                                        width: cropBox.width,
                                        height: cropBox.height
                                    }
                                ]}
                                {...panResponder.panHandlers}
                            >
                                {/* Grid Lines (Optional for better alignment) */}
                                <View style={styles.gridLineVertical} />
                                <View style={styles.gridLineHorizontal} />

                                {/* Resize Handles */}
                                <View style={[styles.corner, styles.topLeft]} {...tlResponder.panHandlers} />
                                <View style={[styles.corner, styles.topRight]} {...trResponder.panHandlers} />
                                <View style={[styles.corner, styles.bottomLeft]} {...blResponder.panHandlers} />
                                <View style={[styles.corner, styles.bottomRight]} {...brResponder.panHandlers} />
                            </View>

                            {/* Dark Overlays surrounding the crop box */}
                            {/* Top */}
                            <View style={[styles.overlay, { top: 0, left: 0, width: '100%', height: cropBox.y }]} />
                            {/* Bottom */}
                            <View style={[styles.overlay, { top: cropBox.y + cropBox.height, left: 0, width: '100%', height: layout.height - (cropBox.y + cropBox.height) }]} />
                            {/* Left */}
                            <View style={[styles.overlay, { top: cropBox.y, left: 0, width: cropBox.x, height: cropBox.height }]} />
                            {/* Right */}
                            <View style={[styles.overlay, { top: cropBox.y, left: cropBox.x + cropBox.width, width: layout.width - (cropBox.x + cropBox.width), height: cropBox.height }]} />
                        </>
                    )}
                </View>

                {/* Instructions Footer */}
                <View style={styles.footer}>
                    <Text style={styles.footerText}>Drag corners to crop the question</Text>
                </View>
            </View>
        </Modal>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: '#000',
    },
    header: {
        flexDirection: 'row',
        justifyContent: 'space-between',
        paddingHorizontal: 20,
        paddingTop: 50, // Safe area
        paddingBottom: 20,
        backgroundColor: '#000',
        zIndex: 10,
    },
    headerBtn: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 5,
    },
    headerText: {
        color: '#fff',
        fontSize: 16,
    },
    cropContainer: {
        flex: 1,
        justifyContent: 'center',
        alignItems: 'center',
        position: 'relative',
        backgroundColor: '#111',
    },
    image: {
        width: '100%',
        height: '100%',
    },
    overlay: {
        position: 'absolute',
        backgroundColor: 'rgba(0, 0, 0, 0.8)', // Darker overlay for better contrast
    },
    cropBox: {
        position: 'absolute',
        borderWidth: 3, // Thicker border
        borderColor: '#FFD700', // Gold color for maximum visibility
        backgroundColor: 'transparent',
        zIndex: 20,
    },
    gridLineVertical: {
        position: 'absolute',
        left: '33%',
        width: 1,
        height: '100%',
        backgroundColor: 'rgba(255, 215, 0, 0.5)', // Gold grid
    },
    gridLineHorizontal: {
        position: 'absolute',
        top: '33%',
        width: '100%',
        height: 1,
        backgroundColor: 'rgba(255, 215, 0, 0.5)', // Gold grid
    },
    corner: {
        position: 'absolute',
        width: 30,
        height: 30,
        backgroundColor: '#FFF', // White handles
        borderColor: '#FFD700', // Gold border
        borderWidth: 3,
        borderRadius: 15, // Round handles
    },
    topLeft: { top: -15, left: -15 },
    topRight: { top: -15, right: -15 },
    bottomLeft: { bottom: -15, left: -15 },
    bottomRight: { bottom: -15, right: -15 },
    footer: {
        padding: 30,
        backgroundColor: '#000',
        alignItems: 'center',
    },
    footerText: {
        color: '#FFD700', // Gold text
        fontSize: 18,
        fontWeight: 'bold',
    }
});

export default CustomImageCropper;
