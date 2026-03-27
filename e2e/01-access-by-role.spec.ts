import { test, expect } from '@playwright/test';
import { login, screenshot } from './fixtures/test-helpers';
import { Role } from './fixtures/credentials';

/**
 * QA Plan Section 1: Login y acceso por rol
 * Tag: @readonly — runs on both dev and staging
 */

function navTest(role: Role, shouldSee: string[], shouldNotSee: string[]) {
  test(`${role} navigation access @readonly`, async ({ page, baseURL }) => {
    await login(page, baseURL, role);

    // Wait for sidebar to render
    await page.waitForSelector('.fi-sidebar-nav', { timeout: 10_000 }).catch(() => {});

    await screenshot(page, `01-nav-${role}`);

    // Items that SHOULD be visible
    for (const label of shouldSee) {
      const link = page.locator('.fi-sidebar-nav a, .fi-sidebar-nav button').filter({ hasText: label });
      await expect(link.first()).toBeVisible({ timeout: 5_000 });
    }

    // Items that SHOULD NOT be visible
    for (const label of shouldNotSee) {
      const link = page.locator('.fi-sidebar-nav a').filter({ hasText: new RegExp(`^\\s*${label}\\s*$`) });
      await expect(link).toHaveCount(0, { timeout: 3_000 });
    }
  });
}

// super_admin: acceso completo
navTest('super_admin',
  ['Escritorio', 'Dashboard Financiero', 'Financiamientos', 'Transacciones', 'Compañías', 'Miembros Del Fondo', 'Historial de Cierres', 'Cierre Mensual', 'Parámetros', 'Usuarios'],
  []
);

// operator: operaciones + administración parcial
navTest('operator',
  ['Escritorio', 'Financiamientos', 'Transacciones', 'Compañías', 'Clientes'],
  ['Miembros Del Fondo', 'Parámetros', 'Usuarios', 'Cierre Mensual']
);

// member: solo lectura limitada
navTest('member',
  ['Escritorio', 'Estado de Cuenta', 'Historial de Cierres', 'Financiamientos'],
  ['Transacciones', 'Compañías', 'Parámetros', 'Usuarios']
);

// company_user: solo su compañía
navTest('company_user',
  ['Escritorio', 'Financiamientos', 'Transacciones'],
  ['Miembros Del Fondo', 'Parámetros', 'Usuarios', 'Cierre Mensual']
);
