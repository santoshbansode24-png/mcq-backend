import React, { useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView, Alert, ActivityIndicator, StatusBar, Platform } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import RazorpayCheckout from 'react-native-razorpay';
import { useTheme } from '../context/ThemeContext';
import config, { ENABLE_PAYMENTS } from '../api/config';
import AsyncStorage from '@react-native-async-storage/async-storage';

const SubscriptionScreen = ({ navigation }) => {
    const { theme, isDarkMode } = useTheme();
    const [loading, setLoading] = useState(false);

    // If payments are disabled, show "Coming Soon" or redirect
    if (!ENABLE_PAYMENTS) {
        return (
            <View style={[styles.container, { backgroundColor: theme.background, justifyContent: 'center', alignItems: 'center' }]}>
                <Ionicons name="diamond-outline" size={80} color={theme.textSecondary} />
                <Text style={[styles.title, { color: theme.text, marginTop: 20 }]}>Premium Features</Text>
                <Text style={{ color: theme.textSecondary, marginTop: 10 }}>Coming Soon!</Text>
                <TouchableOpacity onPress={() => navigation.goBack()} style={[styles.btn, { marginTop: 30, backgroundColor: theme.primary }]}>
                    <Text style={styles.btnText}>Go Back</Text>
                </TouchableOpacity>
            </View>
        );
    }

    const buySubscription = async () => {
        setLoading(true);
        try {
            const userId = await AsyncStorage.getItem('user_id');
            const userPhone = await AsyncStorage.getItem('user_phone') || '9999999999';
            const userEmail = await AsyncStorage.getItem('user_email') || 'student@example.com';

            if (!userId) {
                Alert.alert("Error", "Please login again.");
                return;
            }

            // 1. Create Order on Backend
            const response = await fetch(`${config.API_URL}/create_order.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: userId,
                    amount: 99 // Amount in INR
                })
            });

            const data = await response.json();

            if (data.status !== 'success') {
                throw new Error(data.message || "Failed to create order");
            }

            // 2. Open Razorpay Checkout
            const options = {
                description: 'Premium Subscription',
                image: 'https://i.imgur.com/3g7nmJC.png', // Replace with your logo URL
                currency: 'INR',
                key: data.key_id,
                amount: data.amount * 100, // Amount is already in paise from backend, but double check. Backend sends 9900.
                name: 'Veeru App',
                order_id: data.order_id,
                prefill: {
                    email: userEmail,
                    contact: userPhone,
                    name: 'Student'
                },
                theme: { color: theme.primary }
            };

            RazorpayCheckout.open(options).then(async (checkoutData) => {
                // 3. Payment Success -> Verify on Backend
                verifyPayment(checkoutData, userId);
            }).catch((error) => {
                // Payment Cancelled or Failed
                Alert.alert("Payment Cancelled", `Error: ${error.description}`);
                setLoading(false);
            });

        } catch (error) {
            console.error(error);
            Alert.alert("Error", error.message);
            setLoading(false);
        }
    };

    const verifyPayment = async (checkoutData, userId) => {
        try {
            const response = await fetch(`${config.API_URL}/verify_payment.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: userId,
                    razorpay_order_id: checkoutData.razorpay_order_id,
                    razorpay_payment_id: checkoutData.razorpay_payment_id,
                    razorpay_signature: checkoutData.razorpay_signature
                })
            });

            const data = await response.json();
            setLoading(false);

            if (data.status === 'success') {
                Alert.alert("Success!", "Welcome to Premium!", [
                    { text: "OK", onPress: () => navigation.navigate('Home') }
                ]);
            } else {
                Alert.alert("Verification Failed", data.message);
            }
        } catch (error) {
            setLoading(false);
            Alert.alert("Error", "Payment verification failed. Please contact support.");
        }
    };

    return (
        <ScrollView contentContainerStyle={[styles.container, { backgroundColor: theme.background }]}>
            <View style={styles.header}>
                <TouchableOpacity onPress={() => navigation.goBack()}>
                    <Ionicons name="arrow-back" size={24} color={theme.text} />
                </TouchableOpacity>
                <Text style={[styles.headerTitle, { color: theme.text }]}>Upgrade to Premium</Text>
                <View style={{ width: 24 }} />
            </View>

            <View style={styles.card}>
                <Ionicons name="rocket" size={50} color="#fbbf24" style={{ marginBottom: 10 }} />
                <Text style={[styles.planTitle, { color: theme.text }]}>Unlimited Access</Text>
                <Text style={[styles.price, { color: theme.primary }]}>₹99 <Text style={{ fontSize: 16, color: theme.textSecondary }}>/ month</Text></Text>

                <View style={styles.features}>
                    <FeatureItem text="Unlimited AI Doubts" theme={theme} />
                    <FeatureItem text="Ad-free Experience" theme={theme} />
                    <FeatureItem text="Exclusive Study Notes" theme={theme} />
                    <FeatureItem text="Priority Support" theme={theme} />
                </View>

                <TouchableOpacity
                    style={[styles.buyBtn, { backgroundColor: theme.primary, opacity: loading ? 0.7 : 1 }]}
                    onPress={buySubscription}
                    disabled={loading}
                >
                    {loading ? <ActivityIndicator color="white" /> : <Text style={styles.buyBtnText}>Buy Premium Now</Text>}
                </TouchableOpacity>
            </View>
        </ScrollView>
    );
};

const FeatureItem = ({ text, theme }) => (
    <View style={styles.featureItem}>
        <Ionicons name="checkmark-circle" size={20} color="#22c55e" />
        <Text style={{ color: theme.text, marginLeft: 10, fontSize: 16 }}>{text}</Text>
    </View>
);

const styles = StyleSheet.create({
    container: {
        flexGrow: 1,
        padding: 20,
        paddingTop: Platform.OS === 'android' ? StatusBar.currentHeight + 20 : 60
    },
    header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 30 },
    headerTitle: { fontSize: 20, fontWeight: 'bold' },
    title: { fontSize: 24, fontWeight: 'bold' },
    card: {
        backgroundColor: 'rgba(255,255,255,0.05)',
        padding: 30,
        borderRadius: 20,
        alignItems: 'center',
        borderWidth: 1,
        borderColor: 'rgba(0,0,0,0.1)'
    },
    planTitle: { fontSize: 22, fontWeight: 'bold', marginBottom: 5 },
    price: { fontSize: 36, fontWeight: 'bold', marginBottom: 20 },
    features: { width: '100%', marginBottom: 30 },
    featureItem: { flexDirection: 'row', alignItems: 'center', marginBottom: 15 },
    buyBtn: {
        width: '100%',
        padding: 15,
        borderRadius: 15,
        alignItems: 'center',
        shadowColor: "#000",
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.3,
        shadowRadius: 5,
        elevation: 5
    },
    buyBtnText: { color: 'white', fontSize: 18, fontWeight: 'bold' },
    btn: { padding: 15, borderRadius: 10 },
    btnText: { color: 'white', fontWeight: 'bold' }
});

export default SubscriptionScreen;
