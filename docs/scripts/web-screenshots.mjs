// Takes the website's screenshots for the documentation as prod shows them, in English
// (public/screenshots/web/), German (web/de/) and Spanish (web/es/): headless Chromium over the
// DevTools protocol, 1280x860, dark theme, the language picked with the site's language switcher,
// signed in as the demo agent DemoFrog with the dev sign-in. The dev-only parts (the dev sign-in
// card, the debug toolbar) are removed, and the agents list shows only the demo agents, never
// real users of the local database.
//
// Needs the web app running in dev with the demo agents (symfony console app:demo-agents) and a
// Chromium or Chrome (not Brave, which hides the browser's language from sites):
//
//   CHROME=/path/to/chrome npm run screenshots:web [-- <URL of the web app> [<language> …]]
import { spawn } from 'node:child_process'
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'

const [base = 'https://127.0.0.1:8000', ...languages] = process.argv.slice(2)
const chrome = process.env.CHROME ?? 'chromium'
const port = 9334

// The browser's language for each of the site's: the date and file fields follow it
const locales = { en: 'en_US', de: 'de_DE', es: 'es_ES' }

// [file, path, what to do on the page before the screenshot]; taken signed in as DemoFrog
const shots = [
  ['home', '/'],
  ['my-stats', '/agent-stats'],
  ['medal-details', '/agent-stats', `document.querySelector('.medal-button[title^="Trekker"]').click()`],
  ['timeline', '/agent-stats/timeline'],
  ['timeline-add-event', '/agent-stats/timeline', `document.querySelector('#timeline-add-open').click()`],
  ['import', '/agent-stats/import'],
  ['banners', '/banners'],
  ['agents', '/agents', `document.querySelectorAll('tbody tr').forEach((tr) => { if (!tr.querySelector('a[href^="/agents/Demo"]')) tr.remove() })`],
  ['agent-profile', '/agents/DemoKey'],
  ['profile', '/profile'],
]

const sleep = (ms) => new Promise((r) => setTimeout(r, ms))

/** Takes every screenshot in one language, with a browser of its own in that language. */
async function screenshots(language) {
  const outDir = fileURLToPath(new URL(`../public/screenshots/web/${language === 'en' ? '' : language + '/'}`, import.meta.url))
  mkdirSync(outDir, { recursive: true })

  const locale = locales[language]
  const profile = mkdtempSync(join(tmpdir(), 'agenttools-screenshots-'))
  const browser = spawn(chrome, [
    '--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`,
    '--ignore-certificate-errors', `--lang=${language}`, '--hide-scrollbars', 'about:blank',
  ], { stdio: 'ignore', env: { ...process.env, LANG: `${locale}.UTF-8`, LANGUAGE: `${locale}:${language}`, LC_ALL: `${locale}.UTF-8` } })

  let page
  for (let i = 0; i < 50 && !page; i++) {
    await sleep(200)
    try { page = (await (await fetch(`http://127.0.0.1:${port}/json`)).json()).find((t) => t.type === 'page') } catch {}
  }
  if (!page) {
    browser.kill()
    throw new Error(`${chrome} did not start; set CHROME to a Chromium or Chrome`)
  }

  const ws = new WebSocket(page.webSocketDebuggerUrl)
  await new Promise((r) => ws.addEventListener('open', r))
  let id = 0
  const pending = new Map()
  ws.addEventListener('message', (e) => {
    const msg = JSON.parse(e.data)
    if (msg.id && pending.has(msg.id)) { pending.get(msg.id)(msg); pending.delete(msg.id) }
  })
  const send = (method, params = {}) => new Promise((r) => { const i = ++id; pending.set(i, r); ws.send(JSON.stringify({ id: i, method, params })) })
  const js = async (expression) => (await send('Runtime.evaluate', { expression, awaitPromise: true })).result?.result?.value
  const go = async (path) => { await send('Page.navigate', { url: base + path }); await sleep(3000) }

  /** Removes the dev-only parts and saves the page as <file>.webp. */
  async function capture(file) {
    await js(`
      document.querySelectorAll('.login-dev, .sf-toolbar, [id^=sfwdt], [id^=sfToolbar]').forEach((e) => e.remove());
      document.body.style.paddingBottom = '0';
      document.activeElement?.blur?.();
    `)
    await sleep(500)
    const shot = await send('Page.captureScreenshot', { format: 'webp', quality: 85 })
    writeFileSync(join(outDir, `${file}.webp`), Buffer.from(shot.result.data, 'base64'))
    console.log(`${language}/${file}.webp`)
  }

  try {
    await send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 860, deviceScaleFactor: 1, mobile: false })
    await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: 'dark' }] })
    await send('Page.enable')

    // The language with the site's language switcher: Google labels its button in it then
    await go('/login')
    await js(`document.querySelector('a[href="/locale/${language}"]').click()`)
    await sleep(5000)
    await capture('login')

    // The dev sign-in card is gone from the page after the screenshot: sign in from a fresh one
    await go('/login')
    await js(`document.querySelector('#identifier').value = 'demofrog@demo.example.com'; document.querySelector('.login-dev form').submit()`)
    await sleep(3000)
    if (await js(`location.pathname`) === '/login') {
      throw new Error('The dev sign-in as DemoFrog failed: is the web app running in dev with the demo agents?')
    }

    for (const [file, path, action] of shots) {
      await go(path)
      if (action) {
        await js(action)
        await sleep(1000)
      }
      await capture(file)
    }
  } finally {
    ws.close()
    browser.kill()
    await sleep(500)
    try { rmSync(profile, { recursive: true, force: true }) } catch {}
  }
}

for (const language of languages.length ? languages : Object.keys(locales)) {
  await screenshots(language)
}
