import React, { createContext, useState, useEffect, useContext } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';

const LanguageContext = createContext();

export const translations = {
    en: {
        appLanguage: "App Language",
        darkMode: "Dark Mode",
        changeProfilePic: "Change Profile Picture",
        subscription: "Subscription",
        helpSupport: "Help & Support",
        logout: "Logout",
        home: "Home",
        myExam: "My Exam",
        profile: "Profile",
        selectLanguage: "Select Language",
        cancel: "Cancel",
        // Tabs
        flashcards: "Flashcards",
        mcqs: "MCQs",
        videos: "Videos",
        revision: "Revision",
        notes: "Notes",
        // Sections
        recentChapters: "Recent Chapters",
        viewAll: "View All",
        progress: "Progress",
        welcome: "Welcome",
        subject: "Subject",
        aiTools: "AI Tools",
        dailyBoosters: "Daily Boosters 🚀",
        vocab: "Vocab",
        maths: "Maths",
        classUpdates: "Class Updates 📢",
        checkAnnouncements: "Check latest announcements",
        yourSubjects: "Your Subjects 📖",
        noSubjects: "No subjects found. Pull down to refresh!",
        chapters: "Chapters",
        selectClass: "Select Class",
        copyright: "© 2024 Veeru App. All rights reserved.",
    },
    hi: {
        appLanguage: "ऐप की भाषा",
        darkMode: "डार्क मोड",
        changeProfilePic: "प्रोफ़ाइल चित्र बदलें",
        subscription: "सदस्यता",
        helpSupport: "सहायता और समर्थन",
        logout: "लॉग आउट",
        home: "होम",
        myExam: "मेरी परीक्षा",
        profile: "प्रोफ़ाइल",
        selectLanguage: "भाषा चुनें",
        cancel: "रद्द करें",
        // Tabs
        flashcards: "फ्लैशकार्ड",
        mcqs: "एमसीक्यू",
        videos: "वीडियो",
        revision: "संशोधन",
        notes: "नोट्स",
        // Sections
        recentChapters: "हाल के अध्याय",
        viewAll: "सभी देखें",
        progress: "प्रगति",
        welcome: "स्वागत है",
        subject: "विषय",
        aiTools: "एआई उपकरण",
        dailyBoosters: "दैनिक बूस्टर 🚀",
        vocab: "शब्दावली",
        maths: "गणित",
        classUpdates: "कक्षा अपडेट 📢",
        checkAnnouncements: "नवीनतम घोषणाएँ देखें",
        yourSubjects: "आपके विषय 📖",
        noSubjects: "कोई विषय नहीं मिला। रिफ्रेश करने के लिए नीचे खींचें!",
        chapters: "अध्याय",
        selectClass: "कक्षा चुनें",
        copyright: "© 2024 वीरू ऐप। सर्वाधिकार सुरक्षित।",
    },
    mr: {
        appLanguage: "अॅप भाषा",
        darkMode: "डार्क मोड",
        changeProfilePic: "प्रोफाईल फोटो बदला",
        subscription: "सदस्यता",
        helpSupport: "मदत आणि समर्थन",
        logout: "बाहेर पडा",
        home: "होम",
        myExam: "माझी परीक्षा",
        profile: "प्रोफाईल",
        selectLanguage: "भाषा निवडा",
        cancel: "रद्द करा",
        // Tabs
        flashcards: "फ्लॅशकार्ड्स",
        mcqs: "एमसीक्यू",
        videos: "व्हिडिओ",
        revision: "उजळणी",
        notes: "नोट्स",
        // Sections
        recentChapters: "अलीकडील धडे",
        viewAll: "सर्व पहा",
        progress: "प्रगती",
        welcome: "स्वागत आहे",
        subject: "विषय",
        aiTools: "एआय टूल्स",
        dailyBoosters: "दैनिक बूस्टर 🚀",
        vocab: "शब्दसंग्रह",
        maths: "गणित",
        classUpdates: "वर्ग अपडेट 📢",
        checkAnnouncements: "नवीनतम घोषणा तपासा",
        yourSubjects: "तुमचे विषय 📖",
        noSubjects: "कोणतेही विषय सापडले नाहीत. रिफ्रेश करण्यासाठी खाली ओढा!",
        chapters: "धडे",
        selectClass: "वर्ग निवडा",
        copyright: "© 2024 वीरू अॅप. सर्व हक्क राखीव.",
    }
};

export const LanguageProvider = ({ children }) => {
    const [language, setLanguage] = useState('en');

    useEffect(() => {
        loadLanguage();
    }, []);

    const loadLanguage = async () => {
        try {
            const storedLanguage = await AsyncStorage.getItem('appLanguage');
            if (storedLanguage) {
                setLanguage(storedLanguage);
            }
        } catch (error) {
            console.error('Failed to load language', error);
        }
    };

    const changeLanguage = async (langCode) => {
        try {
            setLanguage(langCode);
            await AsyncStorage.setItem('appLanguage', langCode);
        } catch (error) {
            console.error('Failed to save language', error);
        }
    };

    const t = (key) => {
        return translations[language][key] || key;
    };

    return (
        <LanguageContext.Provider value={{ language, changeLanguage, t }}>
            {children}
        </LanguageContext.Provider>
    );
};

export const useLanguage = () => useContext(LanguageContext);
