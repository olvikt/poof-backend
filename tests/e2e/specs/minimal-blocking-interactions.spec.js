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

  test('D+C(min): courier can switch online state and leave offline runtime overlay', async ({ page }) => {
    const guards = attachRuntimeGuards(page);

    await loginAs(page, {
      login: 'courier@poof.app',
      password: 'password',
      expectedPath: '/courier/orders',
    });

    await page.goto('/courier/orders');
    const onlineToggle = page.getByTestId('courier-online-toggle');
    await expect(onlineToggle).toBeVisible();
    const offlineOverlay = page.getByText('Ви не на лінії').first();
    const toggleTextBefore = ((await onlineToggle.textContent()) ?? '').replace(/\s+/g, ' ').trim();

    if (toggleTextBefore.includes('Не на лінії')) {
      await onlineToggle.click();
    }

    await expect
      .poll(
        async () => ((await onlineToggle.textContent()) ?? '').replace(/\s+/g, ' ').trim(),
        {
          timeout: 20_000,
          message: `Courier online toggle did not reach expected online state. Initial toggle text: "${toggleTextBefore}"`,
        }
      )
      .toContain('На лінії');

    await expect
      .poll(async () => !(await offlineOverlay.isVisible().catch(() => false)), {
        timeout: 20_000,
        message: 'Courier offline overlay "Ви не на лінії" remained visible after online toggle.',
      })
      .toBeTruthy();

    const acceptButton = page.getByTestId('courier-accept-offer');
    if (await acceptButton.isVisible().catch(() => false)) {
      await acceptButton.click();
      await expect(page).toHaveURL(/\/courier\/my-orders/);
    }

    guards.assertHealthy();
  });
});
