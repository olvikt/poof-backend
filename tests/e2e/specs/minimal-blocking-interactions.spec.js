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
    const initialOnlineState = await onlineToggle.getAttribute('data-e2e-online-state');
    const initialBusyState = await onlineToggle.getAttribute('data-e2e-busy');

    if (initialBusyState === '1') {
      throw new Error(
        `Courier online toggle is blocked by active order state (data-e2e-busy=1). ` +
          `Initial toggle text: "${toggleTextBefore}".`
      );
    }

    if (initialOnlineState !== 'online') {
      for (let attempt = 1; attempt <= 3; attempt += 1) {
        await onlineToggle.click({ force: true });

        let reachedOnline = false;
        try {
          await expect
            .poll(
              async () => (await onlineToggle.getAttribute('data-e2e-online-state')) === 'online',
              { timeout: 8_000, message: `Courier toggle online-state did not change on click attempt ${attempt}.` }
            )
            .toBeTruthy();
          reachedOnline = true;
        } catch (_error) {
          reachedOnline = false;
        }

        if (reachedOnline) {
          break;
        }
      }
    }

    const toggleTextAfter = ((await onlineToggle.textContent()) ?? '').replace(/\s+/g, ' ').trim();
    const onlineStateAfter = await onlineToggle.getAttribute('data-e2e-online-state');

    expect(
      onlineStateAfter,
      `Courier online state did not reach "online". Initial text: "${toggleTextBefore}". Final text: "${toggleTextAfter}".`
    ).toBe('online');

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
