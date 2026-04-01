# Accessibility Requirements Specification

## 1. Introduction

This document defines the accessibility standards and technical requirements for the WebApp | 1vCrimeIlheus. The goal is to ensure the application is usable by people with diverse abilities, including visual, auditory, motor, and cognitive impairments, complying with legal standards and best practices for inclusive design.

## 2. Compliance Standards

The project must adhere to the following international and national guidelines:

- **WCAG 2.1 (Web Content Accessibility Guidelines):** Level **AA** compliance is mandatory.
- **eMAG (Modelo de Acessibilidade em Governo Eletrônico):** Adherence to Brazilian government digital accessibility standards where applicable.

## 3. Visual and UI Requirements

### 3.1. Color and Contrast

- **Contrast Ratio:** Text and interactive elements must have a contrast ratio of at least **4.5:1** against the background (Level AA). Large text (18pt+) must have at least 3:1.
- **Color Independence:** Information must not be conveyed solely by color (e.g., error messages must use icons or text labels, not just red borders).
- **Dark Mode:** The interface should support system-preference based dark mode (optional but recommended for visual comfort).

### 3.2. Typography and Layout

- **Resizable Text:** The layout must remain functional when text is resized up to **200%** without assistive technology.
- **Responsive Design:** The interface must adapt to different viewports (Mobile, Tablet, Desktop) without horizontal scrolling for reading text (Reflow).

## 4. Navigation and Interaction

### 4.1. Keyboard Navigation

- **Focus Visible:** All interactive elements (links, buttons, inputs) must have a clearly visible focus indicator (outline/ring) when selected via keyboard.
- **Tab Order:** Navigation order must follow the logical visual flow of the page (Left-to-Right, Top-to-Bottom).
- **No Keyboard Traps:** Users must be able to navigate into and out of all UI components (modals, iframes) using only the keyboard.

### 4.2. Screen Readers (Assistive Technology)

- **Semantic HTML:** Use proper HTML5 tags (`<main>`, `<nav>`, `<article>`, `<header>`, `<footer>`) instead of generic `<div>` soup.
- **ARIA Labels:** Use `aria-label` or `aria-labelledby` for interactive elements that lack visible text (e.g., icon-only buttons).
- **Image Alt Text:** All informative images must have descriptive `alt` attributes. Decorative images must have empty `alt=""`.

## 5. Technical Implementation (HTML, CSS, JavaScript and PHP)

### 5.1. Forms and Input

- **Labels:** All input fields (especially Login and Registration forms) must have associated `<label>` elements linked via `for`/`id` in HTML.
- **Masked Inputs:** For the CPF field (masked), ensure the screen reader announces the expected format. Use `aria-describedby` to link help text.
- **Error Identification:** Validation errors must be programmatically associated with the input field so screen readers announce the error immediately.

### 5.2. Iframe Accessibility (Admin Module)

Given the architecture uses `iframes` for admin pages:

- **Title Attribute:** Every `<iframe>` must have a descriptive `title` attribute (e.g., `title="Admin Dashboard Content"`).
- **Focus Management:** Ensure the keyboard focus can move seamlessly between the main parent window and the iframe content.

### 5.3. Development Tooling

- **Review checklist:** Include accessibility verification in HTML, forms, iframes and keyboard-navigation reviews.
- **Testing:** Use tools like **Lighthouse** or **Axe DevTools**, plus manual validation in the main user flows before releases.

## 6. Specific Component Requirements

| Component | Requirement |
| :--- | :--- |
| **Modals** | Must trap focus inside the modal while open and return focus to the trigger button upon closing. |
| **Navigation Menu** | Must support "Skip to Content" link as the first focusable element on the page. |
| **Tables** | Data tables (e.g., Process Lists) must use `<th>` with `scope="col"` or `scope="row"` headers. |
| **Status Indicators** | Use icons + text (e.g., "Active", "Blocked") instead of just colored dots. |
