import { test, expect } from '@playwright/test';
import { attachRuntimeGuards } from '../helpers/runtime-guards.js';
import { loginAs } from '../helpers/auth.js';

test.describe('minimal blocking interactive lane', () => {
  test('F+A(min): client runtime bootstrap + basic create-form interaction', async ({ page }) => {
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
    await expect(page.getByTestId('client-order-submit')).toBeVisible();

    guards.assertHealthy();
  });

  test('B(min): client authenticated profile page + logout flow stays interactive', async ({ page }) => {
    const guards = attachRuntimeGuards(page);

    await loginAs(page, {
      login: 'client@test.com',
      password: 'password',
      expectedPath: '/client',
    });

    await page.goto('/client/profile');
    await expect(page.getByRole('button', { name: 'Вийти з акаунту' })).toBeVisible();
    await page.getByRole('button', { name: 'Вийти з акаунту' }).click();
    await expect(page).toHaveURL(/\/login/);

    guards.assertHealthy();
  });

  test('D+C(min): courier runtime can go online and expose actionable/searching state', async ({ page }) => {
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
    const searchingState = page.getByText('Пошук замовлень...');

    await expect(async () => {
      const hasAccept = await acceptButton.isVisible().catch(() => false);
      const hasSearching = await searchingState.isVisible().catch(() => false);

      expect(hasAccept || hasSearching).toBeTruthy();
    }).toPass({ timeout: 20_000 });

    if (await acceptButton.isVisible().catch(() => false)) {
      await acceptButton.click();
      await expect(page).toHaveURL(/\/courier\/my-orders/);
    }

    guards.assertHealthy();
  });
});
