# Projekt-Organizer

Eine kleine PHP/JavaScript-Webanwendung zur Projektorganisation mit zweispaltigem Editor, Dateiablage und Azure OpenAI-Integration.

## Features

- Benutzerregistrierung mit Freigabe durch Administrator
- Admin-Dashboard zum Freischalten wartender Benutzer
- CRUD für Projekte und Dokumente
- Zweispaltiger Editor: links Fließtext, rechts Stichpunkte
- Automatische Zusammenfassung von Fließtext in Stichpunkte (Azure OpenAI)
- Generierung von Fließtext aus Stichpunkten, wenn links nichts vorhanden ist
- Änderungs­vorschläge bei ergänzten Stichpunkten inklusive Übernehmen/Löschen
- Datei-Uploads pro Projekt/Dokument inklusive Inline-Anzeige (PDF geeignet)

## Voraussetzungen

- PHP 8.1 oder höher mit aktivem PDO-MySQL-Driver und cURL
- MariaDB/MySQL-Datenbank (eine einzelne Datenbank genügt)
- Azure OpenAI Ressource mit drei Deployments (Summarize, Expand, Revision)

## Installation

1. Repository auf dem Webspace/Server ablegen (z. B. `public/` als Document-Root).
2. Abhängige Verzeichnisse anlegen:

   ```bash
   cp config.sample.php config.php
   mkdir -p uploads
   chmod 775 uploads
   ```

3. `config.php` anpassen:

   ```php
   <?php
   return [
       'db' => [
           'host' => 'localhost',
           'port' => 3306,
           'name' => 'datenbankname',
           'user' => 'db_user',
           'pass' => 'db_pass',
           'charset' => 'utf8mb4',
       ],
       'azure_openai' => [
           'endpoint' => 'https://<resource>.openai.azure.com/',
           'api_key' => 'AZURE_API_KEY',
           'api_version' => '2024-02-15-preview',
           'deployment_summarize' => 'summarize-model',
           'deployment_expand' => 'expand-model',
           'deployment_revision' => 'revision-model',
       ],
       'uploads_dir' => __DIR__ . '/uploads',
   ];
   ```

4. Datenbanktabellen anlegen:

   ```bash
   mysql -u USER -p DATENBANK < schema.sql
   ```

5. Ersten Admin-Benutzer in der Datenbank anlegen (z. B. via SQL). Das Passwort muss mit `password_hash()` erzeugt werden, z. B.:

   ```bash
   php -r "echo password_hash('geheimesPasswort', PASSWORD_DEFAULT), PHP_EOL;"
   ```

   Den ausgegebenen Hash nutzen und folgenden Befehl anpassen:

   ```sql
   INSERT INTO users (username, password_hash, is_admin, is_approved)
   VALUES ('admin', '<HASH-AUSGABE>', 1, 1);
   ```

6. Sicherstellen, dass `public/` von außen erreichbar ist (z. B. via Apache/Nginx Document-Root).

## Azure OpenAI Hinweise

- Die Anwendung erwartet drei unterschiedliche Deployments für Zusammenfassung, Ausformulierung und Änderungs­vorschläge.
- Falls kein Azure-Schlüssel hinterlegt ist, werden die LLM-Funktionen einfach übersprungen.
- Logs zu fehlgeschlagenen Azure-Requests finden sich in den PHP-Error-Logs.

## Hosting-Tipps

- Die Anwendung kommt ohne externe Abhängigkeiten aus und läuft auf gängigen Shared-Hosting-Angeboten mit PHP & MariaDB.
- Dateigrößenlimits können über `php.ini` (`upload_max_filesize`, `post_max_size`) angepasst werden.

## Lizenz

MIT
