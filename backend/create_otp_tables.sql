-- ============================================
-- Password Reset OTP Table
-- Fixed: FK references users(user_id) — the correct PK column name
-- ============================================

CREATE TABLE IF NOT EXISTS password_reset_otps (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_phone_otp (phone_number, otp_code),
    INDEX idx_expires (expires_at)
);

-- Clean up expired OTPs (run this periodically)
DELETE FROM password_reset_otps WHERE expires_at < NOW();
