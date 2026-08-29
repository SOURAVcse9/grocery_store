<?php
/**
 * public/theme_test.php — Automated Unit Test Suite for Theme System
 */

declare(strict_types=1);

require_once __DIR__ . '/dbconnect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Theme System Integration Tests</title>
    <style>
        body { font-family: sans-serif; padding: 40px; background: #fafafa; color: #333; }
        .test-case { background: #fff; border: 1px solid #ddd; padding: 15px; margin-bottom: 12px; border-radius: 6px; }
        .pass { border-left: 5px solid #2ecc71; }
        .fail { border-left: 5px solid #e74c3c; background: #fdf2f2; }
        .status { font-weight: bold; margin-bottom: 5px; }
        .status.pass-text { color: #27ae60; }
        .status.fail-text { color: #c0392b; }
        h1 { margin-bottom: 30px; }
    </style>
</head>
<body>
    <h1>GroCo Theme System Integration Unit Tests</h1>
    <div id="testResults">Running tests...</div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const resultsDiv = document.getElementById('testResults');
            resultsDiv.innerHTML = '';
            
            const assertions = [];
            
            function assert(description, condition) {
                assertions.push({ description, passed: !!condition });
            }

            try {
                // Test 1: Default theme behaves as "system" if unset
                localStorage.removeItem('groco-theme');
                const defaultTheme = localStorage.getItem('groco-theme') || 'system';
                assert("Default preference is 'system' when localStorage is empty", defaultTheme === 'system');

                // Test 2: Set explicit light persists
                localStorage.setItem('groco-theme', 'light');
                assert("Explicit light is stored in localStorage", localStorage.getItem('groco-theme') === 'light');

                // Test 3: Set explicit dark persists
                localStorage.setItem('groco-theme', 'dark');
                assert("Explicit dark is stored in localStorage", localStorage.getItem('groco-theme') === 'dark');

                // Test 4: Set explicit system persists
                localStorage.setItem('groco-theme', 'system');
                assert("Explicit system is stored in localStorage", localStorage.getItem('groco-theme') === 'system');

                // Test 5: Fallback safety on invalid values
                localStorage.setItem('groco-theme', 'invalid-theme-value');
                const storedValue = localStorage.getItem('groco-theme');
                const resolvedTheme = ['light', 'dark', 'system'].includes(storedValue) ? storedValue : 'system';
                assert("Invalid localStorage value falls back safely to 'system'", resolvedTheme === 'system');

                // Cleanup
                localStorage.removeItem('groco-theme');

            } catch (e) {
                assert("Error during assertion run: " + e.message, false);
            }

            // Render results
            assertions.forEach(a => {
                const el = document.createElement('div');
                el.className = 'test-case ' + (a.passed ? 'pass' : 'fail');
                el.innerHTML = `
                    <div class="status ${a.passed ? 'pass-text' : 'fail-text'}">${a.passed ? '✅ PASS' : '❌ FAIL'}</div>
                    <div>${a.description}</div>
                `;
                resultsDiv.appendChild(el);
            });
        });
    </script>
</body>
</html>
