<?php
// ============================================================
// TITAN Demo License Server — Database Configuration
// ============================================================
// INSTRUCTIONS:
// 1. Copy this file, rename to config.php
// 2. Fill in your values from Hostinger hPanel → Databases
// 3. Upload config.php to /public_html/api/ via hPanel File Manager
// 4. NEVER commit config.php to GitHub (it is in .gitignore)
// ============================================================

// --- Database (from hPanel → MySQL Databases) ---
$DB_HOST = 'localhost';               // Always 'localhost' on Hostinger
$DB_NAME = 'YOUR_DATABASE_NAME';     // e.g. u123456789_titandb
$DB_USER = 'YOUR_DATABASE_USERNAME'; // e.g. u123456789_titanuser
$DB_PASS = 'YOUR_DATABASE_PASSWORD'; // Password you chose for DB user

// --- Admin Dashboard ---
$ADMIN_PASSWORD = 'CHOOSE_A_STRONG_PASSWORD'; // Password for /admin/ — change this!

// --- Trial Settings ---
$TRIAL_HOURS = 24; // Hours allowed per machine before trial expires
