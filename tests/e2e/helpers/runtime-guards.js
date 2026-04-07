export function isHandledGeolocationDegradedSignal(entry = '') {
  const text = String(entry || '');

  return text.includes('[POOF:courier-tracker][warn] geolocation_denied_or_error')
    || text.includes('[POOF:courier-tracker][error] geolocation_denied_or_error')
    || text.includes('Geolocation denied by user/browser permissions');
}

export function attachRuntimeGuards(page) {
  const pageErrors = [];
  const consoleErrors = [];
  const failingResponses = [];

  page.on('pageerror', (error) => {
    pageErrors.push(String(error));
  });

  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      if (isHandledGeolocationDegradedSignal(msg.text())) {
        return;
      }
      consoleErrors.push(msg.text());
    }
  });

  page.on('response', (response) => {
    const status = response.status();
    if (status >= 500) {
      failingResponses.push(`${status} ${response.url()}`);
    }
  });

  return {
    assertHealthy() {
      const evidence = [
        ...pageErrors.map((entry) => `pageerror: ${entry}`),
        ...consoleErrors.map((entry) => `console.error: ${entry}`),
        ...failingResponses.map((entry) => `http5xx: ${entry}`),
      ];

      if (evidence.length > 0) {
        throw new Error(`Runtime guard triggered:\n${evidence.join('\n')}`);
      }
    },
  };
}
