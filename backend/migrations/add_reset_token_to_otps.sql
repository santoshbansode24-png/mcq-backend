-- ============================================================
-- Migration: Add reset_token to password_reset_otps
-- Purpose:   Secure the password reset flow by issuing a
--            short-lived, single-use token after OTP verification.
--            reset_password.php now validates this token from DB
--            instead of only checking verified = TRUE.
-- Run once on Railway via phpMyAdmin / MySQL CLI.
-- ============================================================

ALTER TABLE password_reset_otps
    ADD COLUMN IF NOT EXISTS reset_token   VARCHAR(64)  NULL DEFAULT NULL COMMENT 'Secure token issued after OTP is verified',
    ADD COLUMN IF NOT EXISTS token_expires_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Token valid for 15 minutes after OTP verification',
    ADD INDEX IF NOT EXISTS idx_reset_token (reset_token);
