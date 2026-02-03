# Web Application Security Requirements Specification

## 1. Introduction

This document outlines the technical security requirements for the WebApp | 1vCrimeIlheus project. It defines the standards for authentication, authorization, data protection, and infrastructure security to ensure compliance with data privacy regulations (LGPD) and operational integrity.

## 2. Authentication & Session Management

### 2.1. Authentication Mechanism

- **Protocol:** JSON Web Token (JWT).
- **Token Strategy:** Single Long-Lived Access Token.
- **Token Duration (TTL):** **6 Hours** (Fixed).
  - *Rationale:* Covers the majority of a standard work shift without forcing frequent re-logins, balancing usability with security.
- **Storage:** Client-side `sessionStorage`.
  - *Behavior:* The session is cleared automatically when the browser tab or window is closed.

### 2.2. Login Workflow

1.  **Credentials:** User submits `CPF` (masked) and `Password` via `index.php`.
2.  **Validation:** Backend (PHP Class `Usuario`) validates credentials against the MySQL database.
3.  **Issuance:** Upon success, the API returns a signed JWT containing:
    - `sub` (User ID with prefix, e.g., `USR-123`)
    - `role` (Profile ID)
    - `sector` (Sector ID)
    - `exp` (Expiration timestamp: Now + 6h)
4.  **Redirection:** Frontend redirects to the Homepage upon storage of the token.

---

## 3. Password Policy & Account Lockout

### 3.1. Complexity Requirements

All user passwords must adhere to the following strict complexity rules:

- **Minimum Length:** 8 characters.
- **Character Types:** Must contain at least one uppercase letter, one lowercase letter, one number, and one special character (e.g., `!@#$%^&*`).
- **Expiration:** Passwords do not expire automatically.

### 3.2. History & Reuse

- **Constraint:** When changing a password, the new password **must be different** from the current/previous password.

### 3.3. Brute-Force Protection (Lockout)

- **Threshold:** The account is automatically locked after **3 consecutive failed login attempts**.
- **Unlock Mechanism:** **Manual Admin Intervention Only**. There is no automatic cooldown period. The user must contact an Administrator to unlock the account.

### 3.4. Recovery

- **Method:** Password reset links are sent exclusively to the user's registered institutional email.

---

## 4. Data Protection & Encryption

### 4.1. Encryption at Rest

- **Passwords:** Must never be stored in plain text. Use strong hashing algorithms (e.g., `bcrypt` or `Argon2`).
- **Sensitive Data:** Critical identifiers stored in MySQL.

### 4.2. Encryption in Transit

- **Protocol:** All data transmission between Client (Next.js) and Server (PHP API) must occur over **HTTPS** (TLS 1.2 or higher).

### 4.3. Data Masking

- **CPF:** Stored in the database as `VARCHAR` with the mask applied (e.g., `000.000.000-00`).
- **Display:** Frontend must render the CPF masked.
- **Validation:** Backend must strip non-numeric characters before performing uniqueness checks or logic operations.

---

## 5. API Security & Middleware

### 5.1. Authorization Middleware

All protected API routes must implement a middleware that:

1.  Intercepts the HTTP Request.
2.  Extracts the Bearer Token from the Authorization Header.
3.  Verifies the JWT signature and expiration (`exp`).
4.  **RBAC Check:** Validates if the `role` inside the token has permission for the requested resource (refer to `docs/30-security/access-control-matrix.md`).
5.  **Sector Check:** Ensures the user is not accessing data outside their `sector` ID (Tenancy enforcement).

### 5.2. Input Sanitization

- All API inputs must be sanitized to prevent SQL Injection and XSS (Cross-Site Scripting).
- Use Prepared Statements (PDO) for all database interactions within PHP classes.

---

## 6. Infrastructure Security

### 6.1. Admin Interface Isolation

- **Strategy:** Administrative pages are loaded via an `iframe` within `admin/home.php`.
- **Obfuscation:** Internal scripts and logic files should be kept outside the public web root where possible, or protected via `.htaccess` to prevent direct access without a valid session.

### 6.2. Database Security

- **Host:** CloudPanel / MySQL.
- **Access:** Database port should not be exposed to the public internet; access restricted to `localhost` or specific whitelisted application server IPs.
