import { expect, test, type Page } from '@playwright/test'

// Customer domains go through the mock proxy (see playwright.config.ts).
const customerUrl = (host: string, path = '/') => `http://${host}${path}`

async function addDomain(page: Page, board: string, host: string) {
  const section = page.getByTestId(`board-${board}`)
  await page.goto('/dashboard')
  await section.getByLabel('Domain').fill(host)
  await section.getByRole('button', { name: 'Add domain' }).click()
  return page.getByTestId(`domain-${host}`)
}

test('connect a domain, apply to a job through the proxy, remove it', async ({
  page,
}) => {
  const host = `acme-${Date.now()}.test`
  const row = await addDomain(page, 'acme', host)
  await expect(row).toBeVisible()

  // The mock marks *.test as pointed after ~2s and sends webhooks.
  await expect(async () => {
    await page.reload()
    await expect(row).toContainText('Live')
  }).toPass({ timeout: 15_000 })

  await page.goto(customerUrl(host))
  await expect(page.getByRole('heading', { name: 'Acme Robotics' })).toBeVisible()
  await expect(page).toHaveTitle('Jobs at Acme Robotics')

  // Links are built for the customer's domain, not the app's.
  await page.getByRole('link', { name: 'Firmware Engineer' }).click()
  expect(new URL(page.url()).host).toBe(host)

  // A CSRF-protected POST on the custom domain, then a redirect that
  // must stay on that domain.
  await page.getByLabel('Name').fill('Ada')
  await page.getByLabel('Email').fill('ada@example.com')
  await page.getByRole('button', { name: 'Send application' }).click()
  await expect(page.getByRole('status')).toHaveText(
    'Thanks, Ada. Acme Robotics has your application.',
  )
  expect(new URL(page.url()).host).toBe(host)

  await page.goto('/dashboard')
  await row.getByRole('button', { name: 'Remove' }).click()
  await expect(row).toBeHidden()
  const gone = await page.goto(customerUrl(host))
  expect(gone?.status()).toBe(404)
})

test('a domain that does not point at us stays pending', async ({ page }) => {
  const host = `jobs-${Date.now()}.example.com`
  const row = await addDomain(page, 'globex', host)
  await row.getByRole('button', { name: 'Check DNS' }).click()
  await expect(row).toContainText('Waiting for DNS')

  await row.getByRole('button', { name: 'Remove' }).click()
  await expect(row).toBeHidden()
})

test('rejects our own domains', async ({ page }) => {
  await addDomain(page, 'acme', 'admin.hireboard.app')
  await expect(page.getByTestId('board-acme').getByRole('alert')).toHaveText(
    "That's one of our domains.",
  )
})

test('requests that skip the proxy cannot claim a domain', async ({
  request,
}) => {
  const spoofed = await request.get('/', {
    headers: { 'X-Served-For': 'acme.test' },
  })
  expect(spoofed.status()).toBe(403)

  const wrongToken = await request.get('/', {
    headers: {
      'X-Served-For': 'acme.test',
      'X-SaaS-Domains-Auth-Token': 'guess',
    },
  })
  expect(wrongToken.status()).toBe(403)

  // The right token for a domain nobody connected.
  const unknown = await request.get('/', {
    headers: {
      'X-Served-For': 'nobody.test',
      'X-SaaS-Domains-Auth-Token': 'local_proxy_token',
    },
  })
  expect(unknown.status()).toBe(404)
})

test('webhooks need the right token', async ({ request }) => {
  const res = await request.post('/webhooks/saascustomdomains?token=x', {
    data: { event: 'domain_dns_proxy_record_valid', domain: { uuid: 'x' } },
  })
  expect(res.status()).toBe(401)
})

test('unknown domains get a 404', async ({ page }) => {
  const res = await page.goto(customerUrl('unknown.test'))
  expect(res?.status()).toBe(404)
})
