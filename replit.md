# WC CPF/CNPJ Unique Login

## Overview
WordPress/WooCommerce plugin that provides CPF and CNPJ (Brazilian tax ID) unique validation and document-based login functionality. A standalone demo server showcases the validation logic.

## Recent Changes
- 2026-02-24: Initial Replit setup with PHP 8.4 and demo server

## Project Architecture
- **Plugin files**: Root directory contains the WordPress plugin (`wc-cpf-unique-login.php`, `includes/`, `assets/`)
- **Demo server**: `demo/` directory contains a standalone PHP demo showcasing CPF/CNPJ validation
  - `demo/index.php` - Main demo page with validation form
  - `demo/validator.php` - Standalone CPF/CNPJ validation logic

## Running
- PHP built-in server on port 5000 serving the `demo/` directory
- Command: `php -S 0.0.0.0:5000 -t demo`

## Key Plugin Components
- `class-wc-document-auth.php` - Authentication via CPF/CNPJ
- `class-wc-document-validator.php` - Unique document validation on registration
- `class-wc-document-ajax.php` - AJAX real-time validation
- `class-wc-document-lock.php` - Lock document fields after purchase
- `class-wc-document-login-ui.php` - Login form UI modifications
