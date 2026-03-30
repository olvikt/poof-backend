export function attachRuntimeGuards(page) {
  const pageErrors = [];
  const consoleErrors = [];
  const failingResponses = [];

  page.on('pageerror', (error) => {
    pageErrors.push(String(error));
  });

  page.on('console', (msg) => {
    if (msg.type() === 'error') {
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
