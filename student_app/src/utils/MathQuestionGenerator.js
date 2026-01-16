/**
 * Math Question Generator
 * Optimized with Strategy Pattern for scalability.
 */

export const STRATEGIES = {
    // Level 1-5 (Fundamentals)
    ADD_SUB_WITHIN_20: 'ADD_SUB_20',
    MAKE_10: 'MAKE_10',
    SUBTRACT_FROM_10_20: 'SUB_10_20',

    // Level 6-10 (Intermediate)
    ADD_2_DIGIT_SIMPLE: 'ADD_2_DIGIT',
    SUB_2_DIGIT_SIMPLE: 'SUB_2_DIGIT',
    MUL_TABLES_SIMPLE: 'MUL_TABLES',

    // Level 11-20 (Advanced)
    DOUBLE_UP: 'DOUBLE_UP',
    DIV_SIMPLE: 'DIV_SIMPLE',
    MIXED_OPS_SIMPLE: 'MIXED_OPS',
    MUL_TABLES_HARD: 'MUL_TABLES_HARD'
};

// 1. Helper extracted to avoid recreation
const getRandomInt = (min, max) => Math.floor(Math.random() * (max - min + 1)) + min;

// 2. Optimization: Strategy Map (Replaces the big switch statement)
const STRATEGY_HANDLERS = {
    [STRATEGIES.ADD_SUB_WITHIN_20]: () => {
        if (Math.random() > 0.5) {
            const num1 = getRandomInt(1, 15);
            const num2 = getRandomInt(1, 20 - num1);
            return { q: `${num1} + ${num2}`, a: num1 + num2 };
        } else {
            const num1 = getRandomInt(5, 20);
            const num2 = getRandomInt(1, num1);
            return { q: `${num1} - ${num2}`, a: num1 - num2 };
        }
    },
    [STRATEGIES.MAKE_10]: () => {
        let num1, num2;
        do {
            num1 = getRandomInt(5, 9);
            num2 = getRandomInt(2, 9);
        } while (num1 + num2 <= 10);
        return { q: `${num1} + ${num2}`, a: num1 + num2 };
    },
    [STRATEGIES.SUBTRACT_FROM_10_20]: () => {
        const num1 = getRandomInt(10, 19);
        const num2 = getRandomInt(num1 - 9, 9);
        return { q: `${num1} - ${num2}`, a: num1 - num2 };
    },
    [STRATEGIES.ADD_2_DIGIT_SIMPLE]: () => {
        const num1 = getRandomInt(10, 80);
        const num2 = getRandomInt(10, 99 - num1);
        return { q: `${num1} + ${num2}`, a: num1 + num2 };
    },
    [STRATEGIES.SUB_2_DIGIT_SIMPLE]: () => {
        const num1 = getRandomInt(20, 99);
        const num2 = getRandomInt(10, num1 - 5);
        return { q: `${num1} - ${num2}`, a: num1 - num2 };
    },
    [STRATEGIES.MUL_TABLES_SIMPLE]: () => {
        const num1 = getRandomInt(2, 9);
        const num2 = getRandomInt(2, 9);
        return { q: `${num1} × ${num2}`, a: num1 * num2 };
    },
    [STRATEGIES.MUL_TABLES_HARD]: () => {
        const num1 = getRandomInt(2, 12);
        let num2 = getRandomInt(2, 15);
        if (num1 <= 9 && num2 <= 9) num2 = 12; // Force complexity
        return { q: `${num1} × ${num2}`, a: num1 * num2 };
    },
    [STRATEGIES.DOUBLE_UP]: () => {
        const num1 = getRandomInt(12, 99);
        return { q: `${num1} × 2`, a: num1 * 2 };
    },
    [STRATEGIES.DIV_SIMPLE]: () => {
        const divisor = getRandomInt(2, 9);
        const quotient = getRandomInt(2, 12);
        const dividend = divisor * quotient;
        return { q: `${dividend} ÷ ${divisor}`, a: quotient };
    },
    [STRATEGIES.MIXED_OPS_SIMPLE]: () => {
        const num1 = getRandomInt(2, 20);
        const num2 = getRandomInt(2, 20);
        const num3 = getRandomInt(1, 10);
        return { q: `${num1} + ${num2} - ${num3}`, a: num1 + num2 - num3 };
    }
};

export const MathQuestionGenerator = {
    generate: (strategyType) => {
        // Fallback to basic if strategy not found
        const generator = STRATEGY_HANDLERS[strategyType] || STRATEGY_HANDLERS[STRATEGIES.ADD_SUB_WITHIN_20];

        // Execute the strategy
        const { q, a } = generator();

        return {
            id: Date.now().toString(36) + Math.random().toString(36).substr(2), // Unique ID
            question: q,
            answer: a,
            options: MathQuestionGenerator.generateOptions(a),
            strategy: strategyType
        };
    },

    generateOptions: (correctAnswer) => {
        const options = new Set([correctAnswer]);
        let attempts = 0;

        // 3. Optimization: Loop safety to prevent freezing if options can't be found
        while (options.size < 4 && attempts < 20) {
            attempts++;
            const offset = getRandomInt(1, 5);
            const direction = Math.random() > 0.5 ? 1 : -1;
            let wrong = correctAnswer + (offset * direction);

            // Logic to prevent negative options for simple math (unless answer is negative)
            if (correctAnswer >= 0 && wrong < 0) wrong = Math.abs(wrong);

            // Logic to prevent 0 as an option for multiplication/division if desired (optional)
            if (wrong === correctAnswer) continue;

            options.add(wrong);
        }

        // Fill with randoms if we couldn't find close offsets (rare edge case)
        while (options.size < 4) {
            options.add(correctAnswer + getRandomInt(6, 15));
        }

        return Array.from(options).sort(() => Math.random() - 0.5);
    },

    getStrategiesForLevel: (level) => {
        if (level <= 2) return [STRATEGIES.ADD_SUB_WITHIN_20];
        if (level <= 4) return [STRATEGIES.ADD_SUB_WITHIN_20, STRATEGIES.MAKE_10, STRATEGIES.SUBTRACT_FROM_10_20];
        if (level <= 7) return [STRATEGIES.ADD_2_DIGIT_SIMPLE, STRATEGIES.SUB_2_DIGIT_SIMPLE, STRATEGIES.MAKE_10];
        if (level <= 10) return [STRATEGIES.MUL_TABLES_SIMPLE, STRATEGIES.ADD_2_DIGIT_SIMPLE, STRATEGIES.SUB_2_DIGIT_SIMPLE];
        if (level <= 15) return [STRATEGIES.MUL_TABLES_HARD, STRATEGIES.DIV_SIMPLE, STRATEGIES.DOUBLE_UP];
        return Object.values(STRATEGIES);
    },

    getStrategyForLevel: (level) => {
        const strats = MathQuestionGenerator.getStrategiesForLevel(level);
        return strats[Math.floor(Math.random() * strats.length)];
    }
};