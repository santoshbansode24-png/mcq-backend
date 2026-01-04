# Veeru - Educational Learning Platform

**Veeru** is a comprehensive educational learning platform with mobile apps for students and teachers, featuring MCQ tests, video lessons, AI tutoring, vocabulary building, and more.

## 🎯 Project Overview

- **Project Name**: Veeru
- **Database**: veeru_db
- **Bundle ID**: com.veeru.app
- **Version**: 1.0.0

## 📱 Applications

### Student App
- **Location**: `student_app/`
- **Platform**: React Native (Expo)
- **Features**:
  - Subject-wise learning with chapters
  - Video lessons and PDF notes
  - MCQ practice tests
  - AI Tutor for homework help
  - Vocabulary Booster
  - Mental Maths practice
  - Quick Revision flashcards
  - Custom exam generator
  - Progress tracking and leaderboards

### Teacher App
- **Location**: `teacher_app/`
- **Platform**: React Native (Expo)
- **Features**:
  - Class management
  - Student progress monitoring
  - Notification system

### Admin Portal
- **Location**: `backend/admin/`
- **Platform**: PHP Web Application
- **Features**:
  - Content management (subjects, chapters, MCQs)
  - User management
  - Upload videos, notes, and study materials
  - Vocabulary word management
  - Quick revision content management

## 🛠️ Technology Stack

### Backend
- **Language**: PHP 8.1+
- **Database**: MySQL 8.0+
- **Server**: Apache (XAMPP)
- **APIs**: RESTful JSON APIs

### Mobile Apps
- **Framework**: React Native with Expo
- **Navigation**: React Navigation
- **State Management**: React Context API
- **HTTP Client**: Axios
- **UI Components**: Custom components with Expo Linear Gradient

## 🚀 Getting Started

### Prerequisites
- XAMPP (Apache + MySQL + PHP)
- Node.js 18+ and npm
- Expo CLI
- Android Studio (for Android development) or Xcode (for iOS)

### Backend Setup

1. **Start XAMPP**
   ```bash
   # Start Apache and MySQL from XAMPP Control Panel
   ```

2. **Create Database**
   ```bash
   # Open phpMyAdmin (http://localhost/phpmyadmin)
   # Import: backend/database.sql
   # This will create the 'veeru_db' database
   ```

3. **Configure Database**
   - Database credentials are in `backend/config/db.php`
   - Default: localhost, root, no password, database: veeru_db

4. **Access Admin Portal**
   ```
   http://localhost/mcq%20project2.0/backend/admin/
   ```

### Mobile App Setup

1. **Install Dependencies**
   ```bash
   cd student_app
   npm install
   ```

2. **Configure API URL**
   - Edit `student_app/src/api/config.js`
   - Update SERVER_IP to your computer's local IP address

3. **Start Development Server**
   ```bash
   npm start
   # or
   npx expo start
   ```

4. **Run on Device**
   - Scan QR code with Expo Go app (Android/iOS)
   - Or press 'a' for Android emulator
   - Or press 'i' for iOS simulator

## 📊 Database Structure

- **users** - Admin, teachers, and students
- **classes** - Class 1-12
- **subjects** - Subject per class
- **chapters** - Chapters per subject
- **mcqs** - Multiple choice questions
- **videos** - Video lessons
- **notes** - PDF and HTML notes
- **flashcards** - Quick revision cards
- **vocab_words** - Vocabulary words with meanings
- **mental_math_questions** - Mental maths practice
- **badges** - Achievement badges
- **student_progress** - Learning progress tracking
- **notifications** - Teacher-student communication

## 🔐 Default Credentials

### Admin Portal
- **Email**: admin@example.com
- **Password**: admin123

### Student App
- **Email**: student@example.com
- **Password**: student123

### Teacher App
- **Email**: teacher@example.com
- **Password**: teacher123

## 🌐 API Endpoints

Base URL: `http://localhost/mcq%20project2.0/backend/api/`

### Authentication
- `POST /login.php` - User login

### Content
- `GET /get_subjects.php?class_id={id}` - Get subjects
- `GET /get_chapters.php?subject_id={id}` - Get chapters
- `GET /get_mcqs.php?chapter_id={id}` - Get MCQs
- `GET /get_videos.php?chapter_id={id}` - Get videos
- `GET /get_notes.php?chapter_id={id}` - Get notes

### AI Features
- `POST /ai_tutor.php` - AI homework help
- `POST /ai_generate_quiz.php` - Generate custom quiz
- `POST /ai_english_tutor.php` - English conversation practice

### Vocabulary
- `GET /vocab_get_words.php?user_id={id}` - Get vocabulary words
- `POST /vocab_submit_rating.php` - Submit word rating

### Progress
- `GET /get_student_analytics.php?user_id={id}` - Get analytics
- `POST /record_mcq_attempt.php` - Record MCQ attempt
- `GET /get_mcq_leaderboard.php?class_id={id}` - Get leaderboard

## 📁 Project Structure

```
veeru/
├── backend/
│   ├── api/              # REST API endpoints
│   ├── admin/            # Admin web portal
│   ├── config/           # Database configuration
│   ├── uploads/          # User uploaded files
│   ├── database.sql      # Database schema
│   └── Dockerfile        # Docker configuration
├── student_app/
│   ├── src/
│   │   ├── api/          # API client functions
│   │   ├── screens/      # App screens
│   │   ├── components/   # Reusable components
│   │   └── context/      # React context providers
│   ├── assets/           # Images, fonts, icons
│   ├── app.json          # Expo configuration
│   └── package.json      # Dependencies
└── teacher_app/
    └── (similar structure to student_app)
```

## 🚢 Deployment

### Railway.app (Recommended)
- See `implementation_plan.md` for Railway deployment guide
- Cost: ~$5/month
- Includes MySQL database + web hosting

### Local Network
- Use XAMPP on your computer
- Access from mobile devices on same WiFi
- Update `config.js` with your computer's IP address

## 📝 License

Private - Educational Use Only

## 👥 Contributors

- Development Team

## 📞 Support

For issues and questions, please contact the development team.

---

**Veeru** - Empowering Education Through Technology 🎓
