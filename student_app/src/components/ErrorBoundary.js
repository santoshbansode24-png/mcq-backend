import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ScrollView } from 'react-native';

class ErrorBoundary extends React.Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null, errorInfo: null };
    }

    static getDerivedStateFromError(error) {
        return { hasError: true };
    }

    componentDidCatch(error, errorInfo) {
        console.error("Uncaught Error:", error, errorInfo);
        this.setState({ error, errorInfo });
    }

    handleRestart = () => {
        // In production, we might want to restart the app or clear state
        // For now, we will just try to reset the error state and hope the parent re-renders correctly
        // OR simpler: just ask user to reload.
        // Attempting to clear state:
        this.setState({ hasError: false, error: null, errorInfo: null });
        if (this.props.onReset) {
            this.props.onReset();
        }
    };

    render() {
        if (this.state.hasError) {
            return (
                <View style={styles.container}>
                    <ScrollView contentContainerStyle={styles.content}>
                        <Text style={styles.title}>Something went wrong</Text>
                        <Text style={styles.subtitle}>
                            We encountered an error. Please try again.
                        </Text>

                        <View style={styles.errorBox}>
                            <Text style={styles.errorTitle}>Error Details:</Text>
                            <Text style={styles.errorText}>
                                {this.state.error && this.state.error.toString()}
                            </Text>
                            {this.state.errorInfo && (
                                <Text style={styles.stackTrace}>
                                    {this.state.errorInfo.componentStack}
                                </Text>
                            )}
                        </View>

                        <TouchableOpacity style={styles.button} onPress={this.handleRestart}>
                            <Text style={styles.buttonText}>Restart App</Text>
                        </TouchableOpacity>
                    </ScrollView>
                </View>
            );
        }

        return this.props.children;
    }
}

const styles = StyleSheet.create({
    container: {
        flex: 1,
        backgroundColor: '#f8d7da',
        justifyContent: 'center',
        padding: 20,
    },
    content: {
        flexGrow: 1,
        justifyContent: 'center',
        alignItems: 'center',
    },
    title: {
        fontSize: 24,
        fontWeight: 'bold',
        color: '#721c24',
        marginBottom: 10,
    },
    subtitle: {
        fontSize: 16,
        color: '#721c24',
        marginBottom: 20,
        textAlign: 'center',
    },
    errorBox: {
        backgroundColor: '#fff',
        padding: 15,
        borderRadius: 5,
        width: '100%',
        maxHeight: 200,
        marginBottom: 20,
        borderWidth: 1,
        borderColor: '#f5c6cb',
    },
    errorText: {
        color: '#dc3545',
        fontSize: 16,
        fontWeight: 'bold',
        marginBottom: 10,
    },
    errorTitle: {
        fontSize: 18,
        fontWeight: 'bold',
        color: '#333',
        marginBottom: 5,
    },
    stackTrace: {
        fontSize: 10,
        color: '#666',
        fontFamily: 'monospace',
    },
    button: {
        backgroundColor: '#dc3545',
        paddingVertical: 15,
        paddingHorizontal: 30,
        borderRadius: 8,
        elevation: 5,
    },
    buttonText: {
        color: '#fff',
        fontSize: 18,
        fontWeight: 'bold',
    }
});

export default ErrorBoundary;
