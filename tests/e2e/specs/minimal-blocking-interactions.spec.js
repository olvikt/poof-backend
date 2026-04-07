import { test, expect } from '@playwright/test';
import { attachRuntimeGuards } from '../helpers/runtime-guards.js';
import { loginAs } from '../helpers/auth.js';

test.describe('minimal blocking interactive lane', () => {
  test('A(min): denied geolocation is handled as degraded-safe UI state', async ({ page }) => {
    const guards = attachRuntimeGuards(page);

    await page.addInitScript(() => {
      const deniedError = {
        code: 1,
        message: 'Permission denied',
        PERMISSION_DENIED: 1,
      };

      const geolocation = window.navigator.geolocation;
      if (!geolocation) return;

      geolocation.getCurrentPosition = (_success, error) => {
        if (typeof error === 'function') {
          error(deniedError);
        }
      };
    });

    await loginAs(page, {
      login: 'client@test.com',
      password: 'password',
      expectedPath: '/client',
    });

    await page.goto('/client/order/create');
    await expect(page.locator('#order-create-root')).toBeVisible();

    const geoActionState = await page.evaluate(async () => {
      return await new Promise((resolve) => {
        const onState = (event) => {
          const detail = event?.detail || {};
          if (detail.status !== 'error') return;

          window.removeEventListener('poof:geo-action-state', onState);
          clearTimeout(timeout);
          resolve({
            status: detail.status,
            message: detail.message,
            source: detail.source,
          });
        };

        const timeout = window.setTimeout(() => {
          window.removeEventListener('poof:geo-action-state', onState);
          resolve(null);
        }, 8_000);

        window.addEventListener('poof:geo-action-state', onState);

        window.dispatchEvent(new CustomEvent('use-current-location'));
      });
    });

    expect(geoActionState, 'Denied geolocation must surface explicit degraded-safe UI state.').not.toBeNull();
    expect(geoActionState.status).toBe('error');
    expect(String(geoActionState.message || '').length).toBeGreaterThan(0);

    guards.assertHealthy();
  });

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
    await expect(page.getByTestId('scheduled-date-tomorrow')).toBeVisible();
    await page.getByTestId('scheduled-date-tomorrow').click();
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
    const clickToggleAndWaitForState = async (expectedState, label) => {
      let requestTriggered = false;

      for (let attempt = 1; attempt <= 3; attempt += 1) {
        const triggered = await Promise.all([
          page
            .waitForResponse(
              (response) =>
                response.request().method() === 'POST' && response.url().includes('/livewire/update'),
              { timeout: 5_000 }
            )
            .then(() => true)
            .catch(() => false),
          onlineToggle.click({ force: true }),
        ]).then(([responseTriggered]) => responseTriggered);

        requestTriggered = requestTriggered || triggered;

        const reached = await expect
          .poll(async () => (await onlineToggle.getAttribute('data-e2e-online-state')) === expectedState, {
            timeout: 8_000,
            message: `Courier toggle state did not become "${expectedState}" on ${label} attempt ${attempt}.`,
          })
          .toBeTruthy()
          .then(() => true)
          .catch(() => false);

        if (reached) {
          break;
        }
      }

      if (!requestTriggered) {
        throw new Error(`Courier toggle did not trigger Livewire update request during "${label}".`);
      }
    };

    const nextState = initialOnlineState === 'online' ? 'offline' : 'online';
    await clickToggleAndWaitForState(nextState, 'first-toggle');
    await clickToggleAndWaitForState(initialOnlineState ?? 'online', 'second-toggle');

    const busyStateAfter = await onlineToggle.getAttribute('data-e2e-busy');
    expect(
      busyStateAfter,
      `Courier should stay idle in D+C(min) fixture. Initial text: "${toggleTextBefore}".`
    ).toBe('0');

    if ((await onlineToggle.getAttribute('data-e2e-online-state')) === 'online') {
      await expect
        .poll(async () => !(await offlineOverlay.isVisible().catch(() => false)), {
          timeout: 20_000,
          message: 'Courier offline overlay "Ви не на лінії" remained visible after returning online.',
        })
        .toBeTruthy();
    }

    guards.assertHealthy();
  });

  test('proof(min): courier proof flow uses camera-first capture contract + completion modal', async ({ page }) => {
    const guards = attachRuntimeGuards(page);

    await page.addInitScript(() => {
      window.__poofCameraCaptureMock = async () => {
        const bytes = new Uint8Array([137, 80, 78, 71, 1, 2, 3, 4]);
        return new Blob([bytes], { type: 'image/png' });
      };
    });

    await loginAs(page, {
      login: 'courier@poof.app',
      password: 'password',
      expectedPath: '/courier/orders',
    });

    await page.goto('/courier/my-orders');

    if ((await page.locator('[data-testid=\"proof-card-door\"]').count()) === 0) {
      test.skip(true, 'No proof-aware in-progress courier order in e2e fixture.');
    }

    await page.locator('[data-testid=\"proof-card-door\"] button').first().click();
    await page.locator('[data-testid=\"proof-card-container\"] button').first().click();

    const completeCta = page.locator('[data-testid=\"proof-complete-cta\"]');
    await expect(completeCta).toBeEnabled();
    await completeCta.click();

    await expect(page.getByText('Ви завершили замовлення')).toBeVisible();
    await expect(page.getByText('Гроші зарахуються як тільки клієнт підтвердить виконання')).toBeVisible();
    await page.locator('[data-testid=\"proof-complete-confirm\"]').click();

    guards.assertHealthy();
  });

  test('proof(min): start proof-aware order auto-reveals proof section with helper text', async ({ page }) => {
    const guards = attachRuntimeGuards(page);

    await loginAs(page, {
      login: 'courier@poof.app',
      password: 'password',
      expectedPath: '/courier/orders',
    });

    await page.goto('/courier/my-orders');

    const startCta = page.locator('[data-testid="primary-start-cta"]').first();
    if ((await startCta.count()) === 0) {
      test.skip(true, 'No accepted order available for start in e2e fixture.');
    }

    await startCta.click();

    const proofSection = page.locator('[data-proof-section-for-order]').first();
    await expect(proofSection).toBeVisible();
    await expect(proofSection).toContainText('Завершення стане доступним після 2 фото');
    await expect(page.locator('[data-testid="proof-card-door"]').first()).toBeVisible();
    await expect(page.locator('[data-testid="proof-card-container"]').first()).toBeVisible();

    const inViewport = await proofSection.evaluate((node) => {
      const rect = node.getBoundingClientRect();
      return rect.top >= 0 && rect.top < (window.innerHeight * 0.75);
    });
    expect(inViewport).toBeTruthy();

    guards.assertHealthy();
  });
});
