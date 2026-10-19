<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Symfony Playground Two</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style><?= file_get_contents(__DIR__.'/page.css') ?></style>
</head>
<body>
    <header class="site-header">
        <div class="container py-3 d-flex align-items-center gap-3">
            <span class="fs-5 fw-bold">⚡ Symfony Playground Two</span>
            <img src="https://github.com/elkuku/symfony-playground-two/actions/workflows/tests.yml/badge.svg" alt="Tests" height="20">
        </div>
    </header>

    <main class="container py-5">
        <div class="mb-5">
            <h1 class="fw-bold mb-2">Symfony Playground Two</h1>
            <p class="text-secondary lead">An opinionated Symfony project template with user management, PostgreSQL, and social OAuth login (Google, GitHub, GitLab).</p>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card h-100 text-center p-3">
                    <div class="card-title text-secondary mb-2">Test Coverage</div>
                    <div class="stat-value mb-2">
                        <span class="badge <?= $coverageBadgeClass ?> fs-5 fw-semibold"><?= $coverageDisplay ?></span>
                    </div>
                    <div class="text-secondary small"><?= $coveredStatements ?> / <?= $totalStatements ?> statements</div>
                    <a href="coverage/index.html" class="btn btn-outline-secondary btn-sm mt-3">View Report →</a>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card h-100 p-3">
                    <div class="card-title text-secondary mb-3">Tech Stack</div>
                    <ul class="list-unstyled mb-0">
                        <li class="d-flex justify-content-between py-2 border-bottom border-secondary">
                            <span class="text-secondary">PHP</span><code><?= $phpVersion ?></code>
                        </li>
                        <li class="d-flex justify-content-between py-2 border-bottom border-secondary">
                            <span class="text-secondary">Symfony</span><code><?= $sfVersion ?></code>
                        </li>
                        <li class="d-flex justify-content-between py-2">
                            <span class="text-secondary">Doctrine ORM</span><code><?= $doctrineVersion ?></code>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card h-100 p-3">
                    <div class="card-title text-secondary mb-3">Codebase</div>
                    <ul class="list-unstyled mb-0">
                        <li class="d-flex justify-content-between py-2 border-bottom border-secondary">
                            <span class="text-secondary">Source files</span><code><?= $srcFiles ?></code>
                        </li>
                        <li class="d-flex justify-content-between py-2 border-bottom border-secondary">
                            <span class="text-secondary">Test files</span><code><?= $testFiles ?></code>
                        </li>
                        <li class="d-flex justify-content-between py-2">
                            <span class="text-secondary">Database</span><code>PostgreSQL</code>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card h-100 p-3">
                    <div class="card-title text-secondary mb-3">Links</div>
                    <ul class="list-unstyled mb-0">
                        <li class="py-2 border-bottom border-secondary">
                            <a href="https://github.com/elkuku/symfony-playground-two">GitHub Repository</a>
                        </li>
                        <li class="py-2 border-bottom border-secondary">
                            <a href="coverage/index.html">Coverage Report</a>
                        </li>
                        <li class="py-2">
                            <a href="https://github.com/elkuku/symfony-playground-two/actions">CI Runs</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <p class="text-secondary small text-end">Built on <?= $buildDate ?></p>
    </main>
</body>
</html>
