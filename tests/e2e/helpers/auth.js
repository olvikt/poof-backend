import { expect } from '@playwright/test';

export async function loginAs(page, { login, password, expectedPath }) {
  await page.goto('/login');
  await page.getByPlaceholder('Email або телефон').fill(login);
  await page.getByPlaceholder('Пароль').fill(password);
  await page.getByRole('button', { name: 'Увійти' }).click();
  await page.waitForLoadState('networkidle');

  const expectedUrl = new RegExp(expectedPath);
  if (expectedUrl.test(page.url())) {
    return;
  }

  const authError = page.getByText('Невірний email/телефон або пароль');
  const hasAuthError = await authError.first().isVisible().catch(() => false);
  const visibleAuthError = hasAuthError
    ? (await authError.first().innerText()).trim()
    : 'not visible';
  const pageSnippet = (await page.locator('body').innerText()).slice(0, 600).trim();

  throw new Error(
    `E2E login failed for "${login}". Expected URL pattern "${expectedPath}" but got "${page.url()}". ` +
      `Auth error: ${visibleAuthError}. Page snippet: ${pageSnippet}`
  );
}
