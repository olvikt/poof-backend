import { expect } from '@playwright/test';

export async function loginAs(page, { login, password, expectedPath }) {
  await page.goto('/login');
  await page.getByPlaceholder('Email або телефон').fill(login);
  await page.getByPlaceholder('Пароль').fill(password);
  await page.getByRole('button', { name: 'Увійти' }).click();
  await expect(page).toHaveURL(new RegExp(expectedPath));
}
