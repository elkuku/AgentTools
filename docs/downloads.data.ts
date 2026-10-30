import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

// What the Pages workflow built next to the docs: the dev APK and the test coverage
// (web/docs/, copied into the site after the build); empty in a local build.
const webDocs = fileURLToPath(new URL('../web/docs/', import.meta.url))
const web = fileURLToPath(new URL('../web/', import.meta.url))
const app = fileURLToPath(new URL('../app/', import.meta.url))

/** The number of PHP files in a folder of web/, with its subfolders. */
function phpFiles(folder: string): number {
  return (readdirSync(web + folder, { recursive: true }) as string[]).filter((f) => f.endsWith('.php')).length
}

/** The Kotlin files of the app: the tests and the rest. */
function kotlinFiles(): { source: number; test: number } {
  const files = (readdirSync(app + 'app/src', { recursive: true }) as string[]).filter((f) => f.endsWith('.kt'))
  const test = files.filter((f) => /^(test|androidTest)\//.test(f)).length
  return { source: files.length - test, test }
}

/** A version from the version catalog (gradle/libs.versions.toml). */
function catalogVersion(catalog: string, name: string): string {
  return catalog.match(new RegExp(`^${name} = "([^"]+)"`, 'm'))?.[1] ?? ''
}

/** A setting from the app's build script, e.g. minSdk = 26. */
function buildSetting(script: string, name: string): string {
  return script.match(new RegExp(`\\b${name} = "?([^"\\s]+)"?`))?.[1] ?? ''
}

export interface Downloads {
  devApkVersion: string | null
  devApkSize: string | null
  coverage: string | null
  coveredStatements: number | null
  statements: number | null
  php: string
  symfony: string
  doctrine: string
  sourceFiles: number
  testFiles: number
  app: {
    version: string
    kotlin: string
    agp: string
    composeBom: string
    ktor: string
    coil: string
    osmdroid: string
    minSdk: string
    targetSdk: string
    compileSdk: string
    sourceFiles: number
    testFiles: number
  }
  buildDate: string
}

export default {
  watch: [],
  load(): Downloads {
    const apk = webDocs + 'agenttools-dev.apk'
    const clover = webDocs + 'coverage.xml'

    let coverage: string | null = null
    let coveredStatements: number | null = null
    let statements: number | null = null
    if (existsSync(clover)) {
      // The project's metrics are the last ones, after those of each file
      const metrics = [...readFileSync(clover, 'utf8').matchAll(/<metrics [^>]*? statements="(\d+)" coveredstatements="(\d+)"/g)].at(-1)
      if (metrics && Number(metrics[1]) > 0) {
        statements = Number(metrics[1])
        coveredStatements = Number(metrics[2])
        coverage = (coveredStatements / statements * 100).toFixed(1) + '%'
      }
    }

    const composer = JSON.parse(readFileSync(web + 'composer.json', 'utf8'))
    const catalog = readFileSync(app + 'gradle/libs.versions.toml', 'utf8')
    const script = readFileSync(app + 'app/build.gradle.kts', 'utf8')
    const kotlin = kotlinFiles()

    return {
      devApkVersion: process.env.DEV_APK_VERSION || null,
      devApkSize: existsSync(apk) ? (statSync(apk).size / 1024 / 1024).toFixed(1) + ' MB' : null,
      coverage,
      coveredStatements,
      statements,
      php: composer.require?.php ?? '',
      symfony: composer.extra?.symfony?.require ?? '',
      doctrine: composer.require?.['doctrine/orm'] ?? '',
      sourceFiles: phpFiles('src'),
      testFiles: phpFiles('tests'),
      app: {
        version: buildSetting(script, 'versionName'),
        kotlin: catalogVersion(catalog, 'kotlin'),
        agp: catalogVersion(catalog, 'agp'),
        composeBom: catalogVersion(catalog, 'composeBom'),
        ktor: catalogVersion(catalog, 'ktor'),
        coil: catalogVersion(catalog, 'coil'),
        osmdroid: catalogVersion(catalog, 'osmdroid'),
        minSdk: buildSetting(script, 'minSdk'),
        targetSdk: buildSetting(script, 'targetSdk'),
        compileSdk: buildSetting(script, 'compileSdk'),
        sourceFiles: kotlin.source,
        testFiles: kotlin.test,
      },
      buildDate: new Date().toISOString().slice(0, 16).replace('T', ' ') + ' UTC',
    }
  },
}
