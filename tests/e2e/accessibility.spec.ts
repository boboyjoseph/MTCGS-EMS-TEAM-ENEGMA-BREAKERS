import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

async function expectNoAccessibilityViolations(page: Page) {
    const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .analyze();

    const violations = results.violations.map(({ id, impact, help, nodes }) => ({
        id,
        impact,
        help,
        nodes: nodes.map(({ target, failureSummary }) => ({ target, failureSummary })),
    }));

    expect(violations).toEqual([]);
}

test('homepage has no automatically detected WCAG A/AA violations', async ({ page }) => {
    await page.goto('/');
    await expectNoAccessibilityViolations(page);
});

test('login page has no automatically detected WCAG A/AA violations', async ({ page }) => {
    await page.goto('/login');
    await expectNoAccessibilityViolations(page);
});
