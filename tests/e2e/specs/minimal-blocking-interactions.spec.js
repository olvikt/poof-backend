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

    await page.getByTestId('open-address-picker').click();
    await expect(page.getByText('Мої адреси').first()).toBeVisible();

    await page.getByTestId('bottom-sheet-close').click();
    await expect(page.getByText('Мої адреси').first()).toBeHidden();

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
    await page.getByLabel('Вулиця').fill('Test street');
    await page.getByLabel('Дім').fill('99');

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
    await page.getByTestId('client-profile-edit-open').click();

    const updatedName = `Client E2E ${Date.now()}`;
    await page.getByTestId('client-profile-name-input').fill(updatedName);
    await page.getByTestId('client-profile-save').click();

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
    const onlineToggle = page.getByTestId('courier-online-toggle');
    await expect(onlineToggle).toBeVisible();
    if ((await onlineToggle.textContent())?.includes('Не на лінії')) {
      await onlineToggle.click();
    }

    const acceptButton = page.getByTestId('courier-accept-offer');
    await expect(acceptButton).toBeVisible();
    await acceptButton.click();

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
