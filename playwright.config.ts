// playwright.config.ts
import { defineConfig, devices } from '@playwright/test';
import {
    APP,
    CI,
    PATHS,
    TIMEOUTS,
    ARTISAN_ENV,
} from './e2e/config';

export default defineConfig({

    testDir: './e2e',
    testMatch: /(.+\.)?(test|spec)\.[jt]s/,
    testIgnore: [
        '**/_legacy/**',
        '**/node_modules/**',
        '**/dist/**',
        '**/build/**',
    ],
    
    // ==========================================
    // 1. GLOBAL SETUP & TEARDOWN
    // ==========================================
    globalSetup: './e2e/global-setup.ts',
    globalTeardown: './e2e/global-teardown.ts',

    // ==========================================
    // 2. TIMEOUT
    // ==========================================
    timeout: TIMEOUTS.test,
    globalTimeout: TIMEOUTS.globalTimeout,
    expect: {
        timeout: TIMEOUTS.expect,
    },

// ==========================================
    // 3. EXECUTION
    // ==========================================
    fullyParallel: false,
    forbidOnly: CI.isCI,
    retries: CI.isCI ? 2 : 1,
    workers: CI.workers,
    maxFailures: CI.maxFailures,

    // ==========================================
    // 4. REPORTER
    // ==========================================
    reporter: CI.isCI
        ? [
              ['github'],
              [
                  'html',
                  {
                      open: 'never',
                      outputFolder: PATHS.reportDir,
                  },
              ],
              ['list'],
              [
                  'junit',
                  {
                      outputFile: `${PATHS.reportDir}/junit.xml`,
                  },
              ],
          ]
        : [
              [
                  'html',
                  {
                      open: 'never',
                      outputFolder: PATHS.reportDir,
                  },
              ],
              ['list'],
          ],

    // ==========================================
    // 5. OUTPUT DIRECTORY
    // ==========================================
    outputDir: PATHS.outputDir,

// ==========================================
    // 6. GLOBAL USE
    // ==========================================
    use: {
        baseURL: APP.url,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        actionTimeout: 20_000,
        navigationTimeout: 45_000,
        locale: 'id-ID',
        timezoneId: 'UTC',
        ignoreHTTPSErrors: true,
    },

    // ==========================================
    // 7. WEB SERVER
    // ==========================================
    webServer: {
        command: `php -d variables_order=EGPCS -S ${APP.host}:${APP.port} -t public`,
        port: APP.port,
        reuseExistingServer: false,
        gracefulShutdown: {
            signal: 'SIGTERM',
            timeout: 5_000,
        },
        timeout: TIMEOUTS.webServer,
        stdout: 'ignore',
        stderr: 'pipe',
        env: ARTISAN_ENV,
    },

    // ==========================================
    // 8. PROJECTS (BROWSERS)
    // ==========================================
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
            testIgnore: /rate-limit\.spec\.ts/,
        },
        {
            name: 'firefox',
            use: { ...devices['Desktop Firefox'] },
            testIgnore: /rate-limit\.spec\.ts/,
        },
        ...(CI.isCI
            ? [
                  {
                      name: 'webkit',
                      use: { ...devices['Desktop Safari'] },
                      testIgnore: /rate-limit\.spec\.ts/,
                  },
              ]
            : []),

        // Project khusus Rate Limit (tanpa testIgnore)
        {
            name: 'rate-limit-chromium',
            use: { ...devices['Desktop Chrome'] },
            testMatch: /rate-limit\.spec\.ts/,
        },
        {
            name: 'rate-limit-firefox',
            use: { ...devices['Desktop Firefox'] },
            testMatch: /rate-limit\.spec\.ts/,
        },
        ...(CI.isCI
            ? [
                  {
                      name: 'rate-limit-webkit',
                      use: { ...devices['Desktop Safari'] },
                      testMatch: /rate-limit\.spec\.ts/,
                  },
              ]
            : []),
    ],
});