import React, { useState, useEffect, useRef, useCallback } from 'react';
import {
    View, Text, StyleSheet, TouchableOpacity, Animated, Vibration, Dimensions
} from 'react-native';
import { useTheme } from '../../context/ThemeContext';
import { Ionicons } from '@expo/vector-icons';
import ConfettiCannon from 'react-native-confetti-cannon';
import { saveMathProgress } from '../../api/mentalMath';
import LevelRoadmap from './LevelRoadmap'; // New Import

const { width } = Dimensions.get('window');

const AbacusTab = ({ userLevel, maxLevelAllowed, onProgressUpdate, user, sounds }) => {
    const { theme } = useTheme();
    const [gameState, setGameState] = useState('START'); // START, FLASHING, ANSWERING, RESULT
    
    const [currentPlayingLevel, setCurrentPlayingLevel] = useState(userLevel);
    
    const [numberSequence, setNumberSequence] = useState([]);
    const [correctAnswer, setCorrectAnswer] = useState(0);
    const [userAnswer, setUserAnswer] = useState('');
    const [currentFlashIndex, setCurrentFlashIndex] = useState(-1);
    
    // Determine level parameters
    // Format: Level 1 -> 3 numbers, 1 digit, 1000ms speed
    // Level 30 -> 10 numbers, 1-3 digits, 400ms speed
    const getLevelParams = (lvl) => {
        const count = Math.min(3 + Math.floor(lvl / 3), 10);
        
        let maxNumber = 9;
        if (lvl > 5) maxNumber = 19;
        if (lvl > 10) maxNumber = 49;
        if (lvl > 15) maxNumber = 99;
        if (lvl > 25) maxNumber = 999;
        
        const speed = Math.max(1000 - (lvl * 20), 400); // Caps at 400ms
        
        return { count, maxNumber, speed };
    };

    const flashAnim = useRef(new Animated.Value(0)).current;
    const confettiRef = useRef(null);

    const playSound = async (type) => {
        if (!sounds) return;
        try {
            if (type === 'correct' && sounds.correct) await sounds.correct.replayAsync();
            if (type === 'wrong' && sounds.wrong) await sounds.wrong.replayAsync();
            if (type === 'levelup' && sounds.levelup) await sounds.levelup.replayAsync();
        } catch (error) {
            console.log('Error play sound', error);
        }
    };

    const generateSequence = () => {
        const { count, maxNumber } = getLevelParams(currentPlayingLevel);
        let seq = [];
        let total = 0;
        
        for(let i=0; i<count; i++) {
            // First number is always positive. Afterwards it can be subtraction but ensuring we rarely go negative to keep it simple initially
            let isNegative = i > 0 && Math.random() > 0.6 && total > 5;
            
            let num = Math.floor(Math.random() * maxNumber) + 1;
            
            if (isNegative) {
                // Ensure we don't subtract more than we have 90% of time
                if (num > total) num = Math.floor(Math.random() * total) + 1;
                seq.push(-num);
                total -= num;
            } else {
                seq.push(num);
                total += num;
            }
        }
        
        setNumberSequence(seq);
        setCorrectAnswer(total);
        setUserAnswer('');
    };

    const startFlashing = () => {
        generateSequence();
        setGameState('FLASHING');
        setCurrentFlashIndex(0);
    };

    useEffect(() => {
        if (gameState === 'FLASHING' && numberSequence.length > 0) {
            if (currentFlashIndex < numberSequence.length) {
                const { speed } = getLevelParams(currentPlayingLevel);
                
                // Animate flash
                flashAnim.setValue(0);
                Animated.sequence([
                    Animated.timing(flashAnim, { toValue: 1, duration: speed * 0.2, useNativeDriver: true }),
                    Animated.timing(flashAnim, { toValue: 1, duration: speed * 0.6, useNativeDriver: true }),
                    Animated.timing(flashAnim, { toValue: 0, duration: speed * 0.2, useNativeDriver: true })
                ]).start(() => {
                    setCurrentFlashIndex(prev => prev + 1);
                });
            } else {
                // Done flashing
                setTimeout(() => {
                    setGameState('ANSWERING');
                }, 300);
            }
        }
    }, [gameState, currentFlashIndex, numberSequence]);

    const handleKeypadPress = (val) => {
        if (val === 'DEL') {
            setUserAnswer(prev => prev.slice(0, -1));
        } else if (val === 'ENTER') {
            checkAnswer();
        } else {
            // Check length to prevent crazy long inputs
            if (userAnswer.length < 5) {
                setUserAnswer(prev => prev + val);
            }
        }
    };

    const checkAnswer = async () => {
        const isCorrect = parseInt(userAnswer, 10) === correctAnswer;
        
        if (isCorrect) {
            playSound('levelup');
            if (confettiRef.current) confettiRef.current.start();
            setGameState('RESULT');
            
            if (currentPlayingLevel === maxLevelAllowed) {
                const nl = maxLevelAllowed + 1;
                setCurrentPlayingLevel(nl);
                try {
                    await saveMathProgress(user.user_id, 'abacus', nl);
                    onProgressUpdate('abacus', nl);
                } catch(e){}
            } else {
                setCurrentPlayingLevel(currentPlayingLevel + 1);
            }
            
        } else {
            Vibration.vibrate(100);
            playSound('wrong');
            setGameState('RESULT');
        }
    };

    const formatFlashNumber = (num) => {
        if (num > 0 && currentFlashIndex > 0) return `+${num}`;
        return `${num}`;
    };

    return (
        <View style={styles.container}>
            {gameState === 'RESULT' && parseInt(userAnswer, 10) === correctAnswer && (
                <ConfettiCannon
                    Ref={ref => (confettiRef.current = ref)}
                    count={200}
                    origin={{ x: -10, y: 0 }}
                    autoStart={true}
                    fadeOut={true}
                />
            )}

            {gameState === 'START' && (
                <Animated.View style={styles.card}>
                    <View style={[styles.iconCircle, { backgroundColor: '#fee2e2' }]}>
                        <Text style={styles.emoji}>⚡</Text>
                    </View>
                    <Text style={styles.title}>Flash Abacus</Text>
                    <Text style={styles.subtitle}>Level {currentPlayingLevel}</Text>



                    <LevelRoadmap
                        totalLevels={30}
                        maxUnlockedLevel={maxLevelAllowed}
                        currentSelectedLevel={currentPlayingLevel}
                        onSelectLevel={setCurrentPlayingLevel}
                        themeColor="#e11d48"
                    />

                    <TouchableOpacity style={[styles.primaryButton, { backgroundColor: '#e11d48' }]} onPress={startFlashing} activeOpacity={0.8}>
                        <Text style={styles.primaryButtonText}>Start Flashing</Text>
                        <Ionicons name="flash" size={20} color="#fff" style={{ marginLeft: 8 }} />
                    </TouchableOpacity>
                </Animated.View>
            )}

            {gameState === 'FLASHING' && (
                <View style={styles.flashContainer}>
                    <Animated.Text style={[styles.flashNumber, { opacity: flashAnim }]}>
                        {currentFlashIndex < numberSequence.length ? formatFlashNumber(numberSequence[currentFlashIndex]) : ''}
                    </Animated.Text>
                </View>
            )}

            {gameState === 'ANSWERING' && (
                <View style={styles.answeringContainer}>
                    <View style={styles.inputBox}>
                        <Text style={[styles.inputText, userAnswer.length === 0 && { color: '#cbd5e1' }]}>
                            {userAnswer || 'Enter Total'}
                        </Text>
                    </View>
                    
                    <View style={styles.keypad}>
                        {[1, 2, 3, 4, 5, 6, 7, 8, 9, 'DEL', 0, 'ENTER'].map((key) => (
                            <TouchableOpacity 
                                key={key} 
                                style={[
                                    styles.keyBtn, 
                                    key === 'ENTER' && {backgroundColor: theme.primary},
                                    key === 'DEL' && {backgroundColor: '#ef4444'}
                                ]}
                                onPress={() => handleKeypadPress(key)}
                            >
                                <Text style={[
                                    styles.keyTxt, 
                                    (key === 'ENTER' || key === 'DEL') && {color: '#fff', fontSize: 18}
                                ]}>
                                    {key}
                                </Text>
                            </TouchableOpacity>
                        ))}
                    </View>
                </View>
            )}

            {gameState === 'RESULT' && (
                <View style={styles.card}>
                    <View style={styles.iconCircle}>
                        <Text style={styles.emoji}>{parseInt(userAnswer, 10) === correctAnswer ? '🔥' : '😭'}</Text>
                    </View>
                    <Text style={styles.title}>
                        {parseInt(userAnswer, 10) === correctAnswer ? 'Perfect Focus!' : 'Lost count?'}
                    </Text>

                    <View style={{marginVertical: 20, alignItems: 'center'}}>
                        <Text style={{fontSize: 16, color: '#64748b'}}>Correct Answer:</Text>
                        <Text style={{fontSize: 40, fontWeight: 'bold', color: '#22c55e'}}>{correctAnswer}</Text>
                        
                        <Text style={{fontSize: 16, color: '#64748b', marginTop: 10}}>Your Answer:</Text>
                        <Text style={{fontSize: 30, fontWeight: 'bold', color: parseInt(userAnswer, 10) === correctAnswer ? '#22c55e' : '#ef4444'}}>
                            {userAnswer}
                        </Text>
                    </View>

                    <View style={styles.buttonRow}>
                        <TouchableOpacity
                            style={[styles.primaryButton, { backgroundColor: '#e11d48', flex: 1, marginRight: 10 }]}
                            onPress={startFlashing}
                        >
                            <Text style={styles.primaryButtonText}>{parseInt(userAnswer, 10) === correctAnswer ? 'Next Level' : 'Try Again'}</Text>
                        </TouchableOpacity>
                        
                        <TouchableOpacity
                            style={[styles.primaryButton, { backgroundColor: '#475569', flex: 1 }]}
                            onPress={() => setGameState('START')}
                        >
                            <Text style={styles.primaryButtonText}>Menu</Text>
                        </TouchableOpacity>
                    </View>
                </View>
            )}
        </View>
    );
};

const styles = StyleSheet.create({
    container: { flex: 1, padding: 20, justifyContent: 'center' },
    card: {
        backgroundColor: 'rgba(255, 255, 255, 0.85)', // Glass effect
        borderRadius: 30, padding: 25,
        alignItems: 'center', shadowColor: "#e11d48", shadowOffset: { width: 0, height: 10 },
        shadowOpacity: 0.15, shadowRadius: 25, elevation: 10,
        borderWidth: 1, borderColor: 'rgba(255,255,255,0.6)',
    },
    iconCircle: { width: 70, height: 70, borderRadius: 35, justifyContent: 'center', alignItems: 'center', marginBottom: 15, shadowColor: '#e11d48', shadowOpacity: 0.2, shadowRadius: 10, elevation: 5 },
    emoji: { fontSize: 40 },
    title: { fontSize: 26, fontWeight: '800', marginBottom: 5, color: '#1e293b' },
    subtitle: { fontSize: 18, textAlign: 'center', marginBottom: 15, color: '#e11d48', fontWeight: 'bold' },
    rulesContainer: { backgroundColor: '#f1f5f9', padding: 15, borderRadius: 12, marginBottom: 20, width: '100%' },
    ruleText: { fontSize: 15, color: '#475569', textAlign: 'center', marginBottom: 5, fontWeight: '600' },
    primaryButton: { flexDirection: 'row', width: '100%', paddingVertical: 18, borderRadius: 20, justifyContent: 'center', alignItems: 'center' },
    primaryButtonText: { color: '#fff', fontSize: 18, fontWeight: 'bold' },
    buttonRow: { flexDirection: 'row', width: '100%', marginTop: 20 },
    
    // Flashing State
    flashContainer: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    flashNumber: { fontSize: 120, fontWeight: '900', color: '#1e293b', fontFamily: 'NotoSans-Bold' },
    
    // Answering State
    answeringContainer: { flex: 1, justifyContent: 'center', alignItems: 'center' },
    inputBox: {
        backgroundColor: '#fff', width: '100%', padding: 25, borderRadius: 20, alignItems: 'center', marginBottom: 30,
        shadowColor: '#000', shadowOpacity: 0.1, shadowRadius: 10, elevation: 5
    },
    inputText: { fontSize: 48, fontWeight: 'bold', color: '#1e293b' },
    keypad: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'center', gap: 15 },
    keyBtn: {
        width: (width - 100) / 3, height: 75, backgroundColor: '#fff', borderRadius: 20,
        justifyContent: 'center', alignItems: 'center', shadowColor: '#000', shadowOpacity: 0.1, elevation: 3
    },
    keyTxt: { fontSize: 28, fontWeight: '700', color: '#1e293b' }
});

export default AbacusTab;
