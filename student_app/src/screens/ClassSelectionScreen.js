import React, { useState, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, FlatList, ActivityIndicator, Alert, StatusBar } from 'react-native';
import { fetchClasses } from '../api/classes';
import { API_URL } from '../api/config';

const ClassSelectionScreen = ({ navigation, route }) => {
    const { user } = route.params || {};
    const [classes, setClasses] = useState([]);
    const [loading, setLoading] = useState(false);
    const [debugLog, setDebugLog] = useState("Initializing...");

    useEffect(() => {
        loadClasses();
    }, []);

    const loadClasses = async () => {
        setLoading(true);
        try {
            setDebugLog(`Fetching from: ${API_URL}\nStatus: Sending request...`);
            const response = await fetchClasses();

            setDebugLog(prev => prev + `\nResponse: ${JSON.stringify(response).substring(0, 100)}...`);
            console.log("Class API Response:", JSON.stringify(response));

            if (response.status === 'success') {
                // Alert.alert("Debug", `Fetched ${response.data.length} classes`);
                setClasses(response.data);
                setDebugLog(prev => prev + `\nSuccess! Items: ${response.data.length}`);
            } else {
                Alert.alert('Error', response.message);
                setDebugLog(prev => prev + `\nAPI Error: ${response.message}`);
            }
        } catch (error) {
            const errString = error.message || 'Unknown Error';
            Alert.alert('Error', errString);
            console.error('Class Load Error:', error);
            setDebugLog(prev => prev + `\nCATCH Error: ${errString}`);
        } finally {
            setLoading(false);
        }
    };

    const handleClassSelect = (selectedClass) => {
        // Update user object with selected class_id
        const updatedUser = { ...user, class_id: selectedClass.class_id, class_name: selectedClass.class_name };
        navigation.replace('Main', { user: updatedUser });
    };

    const renderClassItem = ({ item }) => (
        <TouchableOpacity
            style={styles.classCard}
            onPress={() => handleClassSelect(item)}
        >
            <View style={styles.iconContainer}>
                <Text style={styles.icon}>🎓</Text>
            </View>
            <View style={styles.infoContainer}>
                <Text style={styles.className}>{item.class_name}</Text>
                <Text style={styles.subText}>Tap to enter</Text>
            </View>
            <Text style={styles.arrow}>›</Text>
        </TouchableOpacity>
    );

    return (
        <View style={styles.container}>
            <StatusBar barStyle="dark-content" backgroundColor="#f8fafc" />
            <View style={styles.header}>
                <Text style={styles.greeting}>Welcome, {user?.name || 'Student'}!</Text>
                <Text style={styles.title}>Select Your Class</Text>
                <Text style={styles.subtitle}>Choose your class (Found: {classes.length})</Text>
            </View>

            {loading ? (
                <ActivityIndicator size="large" color="#4f46e5" style={styles.loader} />
            ) : (
                <>
                    <Text style={styles.debugCount}>Loaded {classes.length} classes</Text>
                    <FlatList
                        data={classes}
                        renderItem={renderClassItem}
                        keyExtractor={(item) => item.class_id.toString()}
                        contentContainerStyle={styles.listContainer}
                        showsVerticalScrollIndicator={true}
                        nestedScrollEnabled={true}
                        onEndReached={() => console.log('Reached end of list')}
                        onEndReachedThreshold={0.5}
                    />
                </>
            )}

            {/* Debug box temporarily removed for better scrolling */}
            {/* <View style={styles.debugBox}>
                <Text style={styles.debugText}>DEBUG INFO:</Text>
                <Text style={styles.debugText}>{debugLog}</Text>
            </View> */}
        </View>
    );
};

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: '#f8fafc',
    },
    header: {
        padding: 24,
        paddingTop: 60,
        backgroundColor: 'white',
        borderBottomLeftRadius: 30,
        borderBottomRightRadius: 30,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.05,
        shadowRadius: 10,
        elevation: 5,
        marginBottom: 20,
    },
    greeting: {
        fontSize: 16,
        color: '#64748b',
        fontWeight: '600',
        marginBottom: 8,
    },
    title: {
        fontSize: 28,
        fontWeight: 'bold',
        color: '#0f172a',
        marginBottom: 8,
    },
    subtitle: {
        fontSize: 16,
        color: '#94a3b8',
        lineHeight: 24,
    },
    listContainer: {
        padding: 20,
    },
    loader: {
        marginTop: 50,
    },
    classCard: {
        backgroundColor: 'white',
        borderRadius: 20,
        padding: 20,
        marginBottom: 16,
        flexDirection: 'row',
        alignItems: 'center',
        shadowColor: '#4f46e5',
        shadowOffset: { width: 0, height: 4 },
        shadowOpacity: 0.1,
        shadowRadius: 12,
        elevation: 3,
        borderWidth: 1,
        borderColor: '#f1f5f9',
    },
    iconContainer: {
        width: 60,
        height: 60,
        borderRadius: 30,
        backgroundColor: '#e0e7ff',
        justifyContent: 'center',
        alignItems: 'center',
        marginRight: 20,
    },
    icon: {
        fontSize: 30,
    },
    infoContainer: {
        flex: 1,
    },
    className: {
        fontSize: 20,
        fontWeight: 'bold',
        color: '#1e293b',
        marginBottom: 4,
    },
    subText: {
        fontSize: 14,
        color: '#64748b',
    },
    arrow: {
        fontSize: 28,
        color: '#cbd5e1',
        fontWeight: '300',
    },
    debugBox: {
        padding: 10,
        backgroundColor: '#000',
        margin: 10,
        borderRadius: 8
    },
    debugText: {
        color: '#0f0',
        fontFamily: 'monospace',
        fontSize: 10
    },
    debugCount: {
        textAlign: 'center',
        padding: 10,
        fontSize: 16,
        fontWeight: 'bold',
        color: '#4f46e5'
    }
});

export default ClassSelectionScreen;
