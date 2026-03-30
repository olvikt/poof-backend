import { test, expect } from '@playwright/test';
import { attachRuntimeGuards } from '../helpers/runtime-guards.js';
import { loginAs } from '../helpers/auth.js';

test.describe('minimal blocking interactive lane', () => {
  test('F: runtime bootstrap + modal/sheet lifecycle on client order create', async ({ page }) => {
    const guards = attachRuntimeGuards(page);

    await loginAs(page, {
      login: 'client@test.com',
      password: 'password',
      expectedPath: '/client',
    });

    await page.goto('/client/order/create');
    await expect(page.locator('#order-create-root')).toBeVisible();

    await page.getByRole('button', { name: 'Обрати збережену' }).click();
    await expect(page.getByRole('heading', { name: 'Мої адреси' })).toBeVisible();

    await page.getByRole('button', { name: '✕' }).click();
    await expect(page.getByRole('heading', { name: 'Мої адреси' })).toBeHidden();

    guards.assertHealthy();
  });

  test('A: client can create order via real interactive flow', async ({ page }) => {
    const guards = attachRuntimeGuards(page);

    await loginAs(page, {
      login: 'client@test.com',
      password: 'password',
      expectedPath: '/client',
    });

    await page.goto('/client/order/create');
    await expect(page.locator('#order-create-root')).toBeVisible();

    await page.locator('input[wire\:model\.live="street"]').fill('Test street');
    await page.locator('input[wire\:model\.live="house"]').fill('99');

    await page.getByRole('button', { name: 'Завтра' }).click();
    await page.getByRole('button', { name: /08:00–10:00|10:00–12:00|12:00–14:00/ }).first().click();
    await page.getByRole('button', { name: /POOF/ }).click();

    await expect(page.getByRole('heading', { name: 'Ваше замовлення прийнято' })).toBeVisible();

    guards.assertHealthy();
  });

  test('B: client profile edit/save survives live interactions', async ({ page }) => {
    const guards = attachRuntimeGuards(page);

    await loginAs(page, {
      login: 'client@test.com',
      password: 'password',
      expectedPath: '/client',
    });

    await page.goto('/client/profile');
    await page.getByRole('button', { name: 'Редагувати' }).click();

    const updatedName = `Client E2E ${Date.now()}`;
    await page.locator('input[autocomplete="name"]').fill(updatedName);
    await page.getByRole('button', { name: 'Зберегти' }).click();

    await expect(page.getByText(updatedName).first()).toBeVisible();

    guards.assertHealthy();
  });

  test('D+C: courier wire:navigate tabs + accept/start/complete critical transitions', async ({ page }) => {
    const guards = attachRuntimeGuards(page);

    await loginAs(page, {
      login: 'courier@poof.app',
      password: 'password',
      expectedPath: '/courier/orders',
    });

    await page.goto('/courier/orders');
    const onlineToggle = page.getByRole('button', { name: /На лінії|Не на лінії/ });
    await expect(onlineToggle).toBeVisible();
    if ((await onlineToggle.textContent())?.includes('Не на лінії')) {
      await onlineToggle.click();
      await expect(page.getByRole('button', { name: /На лінії/ })).toBeVisible();
    }

    await expect(page.getByRole('button', { name: 'Прийняти' })).toBeVisible();
    await page.getByRole('button', { name: 'Прийняти' }).click();

    await expect(page).toHaveURL(/\/courier\/my-orders/);
    await expect(page.getByRole('button', { name: /Почати виконання/ }).first()).toBeVisible();
    await page.getByRole('button', { name: /Почати виконання/ }).first().click();

    await expect(page.getByRole('button', { name: /Завершити( замовлення)?/ }).first()).toBeVisible();
    await page.getByRole('button', { name: /Завершити( замовлення)?/ }).first().click();

    await expect(page.getByText('Активних замовлень немає')).toBeVisible();

    await page.getByRole('link', { name: 'Доступні' }).click();
    await expect(page).toHaveURL(/\/courier\/orders/);

    guards.assertHealthy();
  });
});
