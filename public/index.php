<?php
declare(strict_types=1);

if (!file_exists(__DIR__ . '/../config.php')) {
    die('config.php fehlt. Kopiere config.sample.php und passe die Zugangsdaten an.');
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Projekt-Organizer</title>
    <link rel="stylesheet" href="styles.css" />
</head>
<body>
<div id="app">
    <div id="auth-view" class="card">
        <h1>Projekt-Organizer</h1>
        <div class="tabs">
            <button data-tab="login" class="active">Login</button>
            <button data-tab="register">Registrieren</button>
        </div>
        <div id="login" class="tab-pane active">
            <form id="login-form">
                <label>Benutzername
                    <input type="text" name="username" required />
                </label>
                <label>Passwort
                    <input type="password" name="password" required />
                </label>
                <button type="submit">Anmelden</button>
                <p class="hint" id="login-message"></p>
            </form>
        </div>
        <div id="register" class="tab-pane">
            <form id="register-form">
                <label>Benutzername
                    <input type="text" name="username" required />
                </label>
                <label>Passwort
                    <input type="password" name="password" required />
                </label>
                <button type="submit">Registrierung senden</button>
                <p class="hint" id="register-message"></p>
            </form>
        </div>
    </div>

    <div id="main-view" class="hidden">
        <aside id="sidebar">
            <div class="sidebar-header">
                <h2>Projekte</h2>
                <button id="logout">Logout</button>
            </div>
            <div id="project-list"></div>
            <button id="new-project">+ Neues Projekt</button>
            <div id="admin-area" class="hidden">
                <h3>Admin</h3>
                <button id="refresh-pending">Ausstehende Benutzer laden</button>
                <ul id="pending-users"></ul>
            </div>
        </aside>

        <main id="content">
            <section id="document-list" class="panel hidden">
                <div class="panel-header">
                    <h2 id="project-title"></h2>
                    <button id="new-document">+ Neues Dokument</button>
                </div>
                <ul id="documents"></ul>
            </section>

            <section id="editor" class="hidden">
                <div class="editor-header">
                    <div>
                        <h2 id="document-title"></h2>
                        <p id="document-meta"></p>
                    </div>
                    <div>
                        <label class="file-upload">
                            <span>Datei hochladen</span>
                            <input type="file" id="file-input" />
                        </label>
                        <ul id="file-list"></ul>
                    </div>
                </div>
                <div class="editor-grid">
                    <div class="column" id="left-column"></div>
                    <div class="column" id="right-column"></div>
                </div>
                <button id="add-block">+ Neuer Abschnitt</button>
            </section>
        </main>
    </div>
</div>
<script src="main.js"></script>
</body>
</html>
