# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: accessibility.spec.ts >> login page has no automatically detected WCAG A/AA violations
- Location: tests\e2e\accessibility.spec.ts:24:1

# Error details

```
Error: expect(received).toEqual(expected) // deep equality

- Expected  -  1
+ Received  + 30

- Array []
+ Array [
+   Object {
+     "help": "Elements must meet minimum color contrast ratio thresholds",
+     "id": "color-contrast",
+     "impact": "serious",
+     "nodes": Array [
+       Object {
+         "failureSummary": "Fix any of the following:
+   Element has insufficient color contrast of 4.33 (foreground color: #6b7280, background color: #f4f1fb, font size: 11.4pt (15.2px), font weight: normal). Expected contrast ratio of 4.5:1",
+         "target": Array [
+           ".form-head > p",
+         ],
+       },
+       Object {
+         "failureSummary": "Fix any of the following:
+   Element has insufficient color contrast of 4.33 (foreground color: #6b7280, background color: #f4f1fb, font size: 10.6pt (14.08px), font weight: normal). Expected contrast ratio of 4.5:1",
+         "target": Array [
+           ".fw-semibold",
+         ],
+       },
+       Object {
+         "failureSummary": "Fix any of the following:
+   Element has insufficient color contrast of 4.33 (foreground color: #6b7280, background color: #f4f1fb, font size: 10.6pt (14.08px), font weight: normal). Expected contrast ratio of 4.5:1",
+         "target": Array [
+           "a[href=\"http://127.0.0.1:8000\"]",
+         ],
+       },
+     ],
+   },
+ ]
```

# Page snapshot

```yaml
- generic [ref=e2]:
  - complementary [ref=e3]:
    - generic [ref=e4]:
      - img "MTCGS" [ref=e5]
      - generic [ref=e6]: MTCGS-EMS
    - generic [ref=e7]:
      - heading "Welcome back to the Employee Management System" [level=1] [ref=e8]
      - paragraph [ref=e9]: Mother Theresa Colegio Group of Schools — one portal for attendance, leave, payroll, and calendars.
      - list [ref=e10]:
        - listitem [ref=e11]:
          - generic [ref=e12]: 
          - text: Secure branch-based access
        - listitem [ref=e13]:
          - generic [ref=e14]: 
          - text: Leave & attendance in one place
        - listitem [ref=e15]:
          - generic [ref=e16]: 
          - text: Payroll made simple
    - generic [ref=e17]: © 2026 MTCGS - All rights reserved
  - main [ref=e18]:
    - generic [ref=e19]:
      - generic [ref=e20]:
        - heading "Sign in" [level=2] [ref=e21]
        - paragraph [ref=e22]: Enter your credentials to access your account.
      - generic [ref=e23]:
        - generic [ref=e24]:
          - generic [ref=e25]: Email Address
          - generic [ref=e26]:
            - generic [ref=e27]: 
            - textbox "Email Address" [active] [ref=e29]:
              - /placeholder: you@example.com
        - generic [ref=e30]:
          - generic [ref=e31]: Password
          - generic [ref=e32]:
            - generic [ref=e33]: 
            - textbox "Password" [ref=e35]:
              - /placeholder: Enter your password
            - generic "Show / hide password" [ref=e36] [cursor=pointer]: 
        - button " Login" [ref=e38] [cursor=pointer]:
          - generic [ref=e39]: 
          - text: Login
      - link " Forgot Password?" [ref=e41] [cursor=pointer]:
        - /url: http://127.0.0.1:8000/forgot-password
        - generic [ref=e42]: 
        - text: Forgot Password?
      - link " Back to Home" [ref=e44] [cursor=pointer]:
        - /url: http://127.0.0.1:8000
        - generic [ref=e45]: 
        - text: Back to Home
```

# Test source

```ts
  1  | import AxeBuilder from '@axe-core/playwright';
  2  | import { expect, test, type Page } from '@playwright/test';
  3  | 
  4  | async function expectNoAccessibilityViolations(page: Page) {
  5  |     const results = await new AxeBuilder({ page })
  6  |         .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
  7  |         .analyze();
  8  | 
  9  |     const violations = results.violations.map(({ id, impact, help, nodes }) => ({
  10 |         id,
  11 |         impact,
  12 |         help,
  13 |         nodes: nodes.map(({ target, failureSummary }) => ({ target, failureSummary })),
  14 |     }));
  15 | 
> 16 |     expect(violations).toEqual([]);
     |                        ^ Error: expect(received).toEqual(expected) // deep equality
  17 | }
  18 | 
  19 | test('homepage has no automatically detected WCAG A/AA violations', async ({ page }) => {
  20 |     await page.goto('/');
  21 |     await expectNoAccessibilityViolations(page);
  22 | });
  23 | 
  24 | test('login page has no automatically detected WCAG A/AA violations', async ({ page }) => {
  25 |     await page.goto('/login');
  26 |     await expectNoAccessibilityViolations(page);
  27 | });
  28 | 
```