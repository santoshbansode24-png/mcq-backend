/**
 * Math Question Generator
 * Optimized with Strategy Pattern for scalability.
 */

export const STRATEGIES = {
    // Phase 1 (Levels 1-5)
    ADD_SUB_UNDER_10: 'ADD_SUB_UNDER_10',
    MAKE_10: 'MAKE_10',

    // Phase 2 (Levels 6-10)
    ADD_CROSS_10: 'ADD_CROSS_10',
    SUB_FROM_TEENS: 'SUB_FROM_TEENS',

    // Phase 3 (Levels 11-15)
    ADD_SUB_MULTIPLES_10: 'ADD_SUB_MULTIPLES_10',
    ADD_1_DIGIT_TO_2_DIGIT_NO_CARRY: 'ADD_1_DIGIT_TO_2_DIGIT_NO_CARRY',

    // Phase 4 (Levels 16-20)
    ADD_2_DIGIT_NO_CARRY: 'ADD_2_DIGIT_NO_CARRY',
    ADD_2_DIGIT_WITH_CARRY: 'ADD_2_DIGIT_WITH_CARRY',
    SUB_2_DIGIT_NO_CARRY: 'SUB_2_DIGIT_NO_CARRY',

    // Phase 5 (Levels 21-25)
    MUL_EASY: 'MUL_EASY', 
    DOUBLE_UP: 'DOUBLE_UP',

    // Phase 6 (Levels 26-30)
    MUL_HARD: 'MUL_HARD',
    DIV_SIMPLE: 'DIV_SIMPLE',
    MIXED_OPS: 'MIXED_OPS'
};

// 1. Helper extracted to avoid recreation
const getRandomInt = (min, max) => Math.floor(Math.random() * (max - min + 1)) + min;

// 2. Optimization: Strategy Map (Replaces the big switch statement)
const STRATEGY_HANDLERS = {
    [STRATEGIES.ADD_SUB_UNDER_10]: () => {
        if (Math.random() > 0.5) {
            const num1 = getRandomInt(1, 8);
            const num2 = getRandomInt(1, 9 - num1);
            return { q: `${num1} + ${num2}`, a: num1 + num2 };
        } else {
            const num1 = getRandomInt(2, 9);
            const num2 = getRandomInt(1, num1 - 1);
            return { q: `${num1} - ${num2}`, a: num1 - num2 };
        }
    },
    [STRATEGIES.MAKE_10]: () => {
        const num1 = getRandomInt(1, 9);
        const num2 = 10 - num1;
        return { q: `${num1} + ${num2}`, a: 10 };
    },
    [STRATEGIES.ADD_CROSS_10]: () => {
        let num1, num2;
        do {
            num1 = getRandomInt(4, 9);
            num2 = getRandomInt(4, 9);
        } while (num1 + num2 <= 10 || num1 + num2 >= 20);
        return { q: `${num1} + ${num2}`, a: num1 + num2 };
    },
    [STRATEGIES.SUB_FROM_TEENS]: () => {
        const num1 = getRandomInt(11, 18);
        const num2 = getRandomInt(num1 - 9, 9); // Result is single digit
        return { q: `${num1} - ${num2}`, a: num1 - num2 };
    },
    [STRATEGIES.ADD_SUB_MULTIPLES_10]: () => {
        if (Math.random() > 0.5) {
            const num1 = getRandomInt(1, 8) * 10;
            const num2 = getRandomInt(1, 9 - (num1/10)) * 10;
            return { q: `${num1} + ${num2}`, a: num1 + num2 };
        } else {
            const num1 = getRandomInt(3, 9) * 10;
            const num2 = getRandomInt(1, (num1/10) - 1) * 10;
            return { q: `${num1} - ${num2}`, a: num1 - num2 };
        }
    },
    [STRATEGIES.ADD_1_DIGIT_TO_2_DIGIT_NO_CARRY]: () => {
        const tens = getRandomInt(1, 8) * 10;
        const ones = getRandomInt(1, 5);
        const num1 = tens + ones;
        const num2 = getRandomInt(1, 9 - ones);
        return { q: `${num1} + ${num2}`, a: num1 + num2 };
    },
    [STRATEGIES.ADD_2_DIGIT_NO_CARRY]: () => {
        const tens1 = getRandomInt(1, 7);
        const ones1 = getRandomInt(1, 7);
        const tens2 = getRandomInt(1, 8 - tens1);
        const ones2 = getRandomInt(1, 8 - ones1);
        const num1 = (tens1 * 10) + ones1;
        const num2 = (tens2 * 10) + ones2;
        return { q: `${num1} + ${num2}`, a: num1 + num2 };
    },
    [STRATEGIES.ADD_2_DIGIT_WITH_CARRY]: () => {
        const tens1 = getRandomInt(1, 7);
        const ones1 = getRandomInt(5, 9);
        const tens2 = getRandomInt(1, 8 - tens1);
        const ones2 = getRandomInt(10 - ones1, 9); // Ensure carry
        const num1 = (tens1 * 10) + ones1;
        const num2 = (tens2 * 10) + ones2;
        return { q: `${num1} + ${num2}`, a: num1 + num2 };
    },
    [STRATEGIES.SUB_2_DIGIT_NO_CARRY]: () => {
        const tens1 = getRandomInt(3, 9);
        const ones1 = getRandomInt(4, 9);
        const tens2 = getRandomInt(1, tens1 - 1);
        const ones2 = getRandomInt(1, ones1 - 1);
        const num1 = (tens1 * 10) + ones1;
        const num2 = (tens2 * 10) + ones2;
        return { q: `${num1} - ${num2}`, a: num1 - num2 };
    },
    [STRATEGIES.MUL_EASY]: () => {
        const easyFactors = [2, 3, 5, 10];
        const num1 = easyFactors[Math.floor(Math.random() * easyFactors.length)];
        const num2 = getRandomInt(2, 9);
        return Math.random() > 0.5 ? { q: `${num1} × ${num2}`, a: num1 * num2 } : { q: `${num2} × ${num1}`, a: num1 * num2 };
    },
    [STRATEGIES.DOUBLE_UP]: () => {
        const num1 = getRandomInt(12, 49);
        return { q: `${num1} × 2`, a: num1 * 2 };
    },
    [STRATEGIES.MUL_HARD]: () => {
        let num1 = getRandomInt(4, 9);
        let num2 = getRandomInt(4, 9);
        if (num1 === 5) num1 = 7; 
        if (num2 === 5) num2 = 8;
        return { q: `${num1} × ${num2}`, a: num1 * num2 };
    },
    [STRATEGIES.DIV_SIMPLE]: () => {
        const divisor = getRandomInt(2, 9);
        const quotient = getRandomInt(2, 9);
        const dividend = divisor * quotient;
        return { q: `${dividend} ÷ ${divisor}`, a: quotient };
    },
    [STRATEGIES.MIXED_OPS]: () => {
        const num1 = getRandomInt(5, 15);
        const num2 = getRandomInt(5, 15);
        const num3 = getRandomInt(2, 9);
        return { q: `${num1} + ${num2} - ${num3}`, a: num1 + num2 - num3 };
    }
};

export const MathQuestionGenerator = {
    generate: (strategyType) => {
        // Fallback to basic if strategy not found
        const generator = STRATEGY_HANDLERS[strategyType] || STRATEGY_HANDLERS[STRATEGIES.ADD_SUB_UNDER_10];

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

        // Fill with deterministic offsets if we couldn't find enough unique options
        let fallbackOffset = 10;
        while (options.size < 4) {
            options.add(correctAnswer + fallbackOffset);
            fallbackOffset += 5;
        }

        return Array.from(options).sort(() => Math.random() - 0.5);
    },

    getStrategiesForLevel: (level) => {
        if (level <= 5) return [STRATEGIES.ADD_SUB_UNDER_10, STRATEGIES.MAKE_10];
        if (level <= 10) return [STRATEGIES.ADD_CROSS_10, STRATEGIES.SUB_FROM_TEENS, STRATEGIES.MAKE_10];
        if (level <= 15) return [STRATEGIES.ADD_SUB_MULTIPLES_10, STRATEGIES.ADD_1_DIGIT_TO_2_DIGIT_NO_CARRY];
        if (level <= 20) return [STRATEGIES.ADD_2_DIGIT_NO_CARRY, STRATEGIES.ADD_2_DIGIT_WITH_CARRY, STRATEGIES.SUB_2_DIGIT_NO_CARRY];
        if (level <= 25) return [STRATEGIES.MUL_EASY, STRATEGIES.DOUBLE_UP];
        return [STRATEGIES.MUL_HARD, STRATEGIES.DIV_SIMPLE, STRATEGIES.MIXED_OPS];
    },

    getStrategyForLevel: (level) => {
        const strats = MathQuestionGenerator.getStrategiesForLevel(level);
        return strats[Math.floor(Math.random() * strats.length)];
    }
};