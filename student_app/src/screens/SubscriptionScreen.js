import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView, Alert, ActivityIndicator, StatusBar, Platform } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import RazorpayCheckout from 'react-native-razorpay';
import { useTheme } from '../context/ThemeContext';
import config, { ENABLE_PAYMENTS } from '../api/config';
import AsyncStorage from '@react-native-async-storage/async-storage';

const SubscriptionScreen = ({ navigation }) => {
    const { theme, isDarkMode } = useTheme();
    const [loading, setLoading] = useState(true);
    const [buying, setBuying] = useState(false);
    const [plans, setPlans] = useState([]);
    const [selectedPlanId, setSelectedPlanId] = useState(null);

    useEffect(() => {
        if (ENABLE_PAYMENTS) {
            fetchPlans();
        }
    }, []);

    const fetchPlans = async () => {
        try {
            const response = await fetch(`${config.API_URL}/get_subscription_plans.php`);
            const data = await response.json();
            if (data.status === 'success') {
                setPlans(data.data);
                if (data.data.length > 0) {
                    setSelectedPlanId(data.data[0].plan_id);
                }
            }
        } catch (error) {
            console.error("Failed to load plans:", error);
            Alert.alert("Error", "Could not load subscription plans.");
        } finally {
            setLoading(false);
        }
    };

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
        if (!selectedPlanId) {
            Alert.alert("Error", "Please select a plan first.");
            return;
        }

        setBuying(true);
        try {
            const userDataStr = await AsyncStorage.getItem('user_data');
            if (!userDataStr) {
                Alert.alert("Error", "Please login again.");
                return;
            }
            
            const user = JSON.parse(userDataStr);
            const userId = user.user_id;
            const userPhone = user.mobile || '9999999999';
            const userEmail = user.email || 'student@example.com';

            if (!userId) {
                Alert.alert("Error", "Invalid user session. Please login again.");
                return;
            }

            // 1. Create Order on Backend using plan_id
            const response = await fetch(`${config.API_URL}/create_order.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    user_id: userId,
                    plan_id: selectedPlanId
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
                amount: Math.round(data.amount * 100), // Amount is in paise from backend, rounded for safety
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
                setBuying(false);
            });

        } catch (error) {
            console.error(error);
            Alert.alert("Error", error.message);
            setBuying(false);
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
            setBuying(false);

            if (data.status === 'success') {
                // Update local storage so premium unlocks immediately without restart
                try {
                    const userDataStr = await AsyncStorage.getItem('user_data');
                    if (userDataStr) {
                        const userData = JSON.parse(userDataStr);
                        userData.subscription_status = 'active';
                        await AsyncStorage.setItem('user_data', JSON.stringify(userData));
                    }
                } catch (e) {
                    console.error("Failed to update local storage:", e);
                }

                Alert.alert("Success!", "Welcome to Premium!", [
                    { text: "OK", onPress: () => navigation.navigate('Main') }
                ]);
            } else {
                Alert.alert("Verification Failed", data.message);
            }
        } catch (error) {
            setBuying(false);
            Alert.alert("Error", "Payment verification failed. Please contact support.");
        }
    };

    if (loading) {
        return (
            <View style={[styles.container, { backgroundColor: theme.background, justifyContent: 'center' }]}>
                <ActivityIndicator size="large" color={theme.primary} />
            </View>
        );
    }

    return (
        <ScrollView contentContainerStyle={[styles.container, { backgroundColor: theme.background }]}>
            <View style={styles.header}>
                <TouchableOpacity onPress={() => navigation.goBack()}>
                    <Ionicons name="arrow-back" size={24} color={theme.text} />
                </TouchableOpacity>
                <Text style={[styles.headerTitle, { color: theme.text }]}>Upgrade to Premium</Text>
                <View style={{ width: 24 }} />
            </View>

            {plans.map((plan) => {
                const isSelected = selectedPlanId === plan.plan_id;
                return (
                    <TouchableOpacity 
                        key={plan.plan_id}
                        style={[styles.card, { borderColor: isSelected ? theme.primary : 'rgba(0,0,0,0.1)' }]}
                        onPress={() => setSelectedPlanId(plan.plan_id)}
                        activeOpacity={0.8}
                    >
                        {isSelected && (
                            <View style={[styles.selectedBadge, { backgroundColor: theme.primary }]}>
                                <Ionicons name="checkmark" size={16} color="white" />
                            </View>
                        )}
                        <Ionicons name={plan.duration_days > 30 ? "rocket" : "star"} size={50} color={isSelected ? theme.primary : "#fbbf24"} style={{ marginBottom: 10 }} />
                        <Text style={[styles.planTitle, { color: theme.text }]}>{plan.plan_name}</Text>
                        <Text style={[styles.price, { color: theme.primary }]}>₹{parseFloat(plan.price)} <Text style={{ fontSize: 16, color: theme.textSecondary }}>/ {plan.duration_days > 30 ? 'year' : 'month'}</Text></Text>

                        <View style={styles.features}>
                            {plan.features_list && plan.features_list.map((feature, idx) => (
                                <FeatureItem key={idx} text={feature} theme={theme} />
                            ))}
                        </View>
                    </TouchableOpacity>
                );
            })}

            <TouchableOpacity
                style={[styles.buyBtn, { backgroundColor: theme.primary, opacity: buying ? 0.7 : 1, marginTop: 10 }]}
                onPress={buySubscription}
                disabled={buying || plans.length === 0}
            >
                {buying ? <ActivityIndicator color="white" /> : <Text style={styles.buyBtnText}>Buy Premium Now</Text>}
            </TouchableOpacity>
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
    headerTitle: { fontSize: 20, fontWeight: 'bold', fontFamily: 'NotoSans-Bold' },
    title: { fontSize: 24, fontWeight: 'bold', fontFamily: 'NotoSans-Bold' },
    card: {
        backgroundColor: 'rgba(255,255,255,0.05)',
        padding: 25,
        borderRadius: 20,
        alignItems: 'center',
        borderWidth: 2,
        marginBottom: 20,
        position: 'relative'
    },
    selectedBadge: {
        position: 'absolute',
        top: 10,
        right: 10,
        width: 30,
        height: 30,
        borderRadius: 15,
        justifyContent: 'center',
        alignItems: 'center'
    },
    planTitle: { fontSize: 22, fontWeight: 'bold', marginBottom: 5, fontFamily: 'NotoSans-Bold' },
    price: { fontSize: 36, fontWeight: 'bold', marginBottom: 20, fontFamily: 'NotoSans-Bold' },
    features: { width: '100%', marginBottom: 10 },
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
        elevation: 5,
        marginBottom: 30
    },
    buyBtnText: { color: 'white', fontSize: 18, fontWeight: 'bold', fontFamily: 'NotoSans-Bold' },
    btn: { padding: 15, borderRadius: 10 },
    btnText: { color: 'white', fontWeight: 'bold' }
});

export default SubscriptionScreen;
