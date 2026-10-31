# Google-Anmeldung einrichten (Android-OAuth-Client)

So richtest du Google Cloud ein, damit `app` sich mit Google anmelden kann und `web` seine
ID-Tokens akzeptiert.

## Wie die Client-IDs zusammenpassen {#how-the-client-ids-fit-together}

Du brauchst **zwei OAuth-Clients im selben Google-Cloud-Projekt**:

| Client          | Wo er verwendet wird                                                     | Zweck                                                                                                     |
|-----------------|--------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------|
| **Web**         | `web` `OAUTH_GOOGLE_ID` und `app` `google.serverClientId`                | Die Zielgruppe (`aud`) des ID-Tokens. Der Server akzeptiert nur Tokens, die für diesen Client ausgestellt sind. |
| **Android**     | Nirgends im Code                                                         | Registriert das Paar *Paketname + SHA-1 des Signaturzertifikats*, damit Google dieser App Tokens ausstellt. |

Die App fordert einen Token **im Namen des Web-Clients** an (`serverClientId`). Google prüft den
Aufrufer gegen den Android-Client (Paket + SHA-1) und gibt einen Token zurück, dessen `aud` die
Web-Client-ID ist. `web` prüft diesen Token gegen `OAUTH_GOOGLE_ID`.

> Trag die Android-Client-ID nicht in die App ein. Sie als `serverClientId` zu verwenden, ist die
> häufigste Ursache für fehlgeschlagene Anmeldungen.

## 1. Das Projekt wählen und den Zustimmungsbildschirm einrichten {#_1-pick-the-project-and-configure-the-consent-screen}

1. Öffne die [Google Cloud Console](https://console.cloud.google.com/) und wähle das Projekt mit dem
   bestehenden **Web**-Client für `web`. Gibt es noch keines, lege ein Projekt an.
2. Geh zu **Google Auth Platform** ([console.cloud.google.com/auth](https://console.cloud.google.com/auth/overview)).
   In älteren Konsolen liegt das unter *APIs & Services → OAuth consent screen*.
3. **Branding**: Setze den App-Namen, die E-Mail für den Benutzersupport und die
   Kontakt-E-Mail des Entwicklers.
4. **Audience**: Wähle *External*. Solange die App im Status **Testing** ist, können sich nur
   eingetragene Testbenutzer anmelden; trag also dein(e) Google-Konto(s) unter **Test users** ein.
5. **Data Access**: Die Standard-Scopes `openid`, `email` und `profile` reichen. Sie sind nicht
   sensibel, es ist also keine Überprüfung nötig.

## 2. Den Web-Client prüfen {#_2-check-the-web-client}

Unter **Clients** (ältere Konsolen: *APIs & Services → Credentials*):

1. Suche den Client **Web application**, den `web` verwendet, oder lege einen an
   (**Create client → Web application**).
2. Kopiere seine **Client ID**; sie sieht aus wie `1234567890-abc….apps.googleusercontent.com`.

Für die Schaltfläche „Sign in with Google“ von `web` (für die App nicht nötig) braucht der
Web-Client außerdem **Authorized JavaScript origins**: z. B. `http://localhost:8000`,
`https://localhost:8000` und die Produktions-URL.

## 3. Den SHA-1 des Signaturzertifikats ermitteln {#_3-get-the-sha-1-of-the-signing-certificate}

Debug-Builds werden mit `~/.android/debug.keystore` signiert. Seinen SHA-1 bekommst du mit Gradle:

```shell
cd app
export JAVA_HOME=~/.local/share/JetBrains/Toolbox/apps/android-studio/jbr
./gradlew signingReport
```

Nimm die Zeile `SHA1:` von `Variant: debug`. Alternativ mit `keytool`:

```shell
keytool -list -v -keystore ~/.android/debug.keystore \
  -alias androiddebugkey -storepass android -keypass android
```

Jeder Rechner hat seinen eigenen Debug-Keystore. Entweder trägt jeder Entwickler seinen eigenen
SHA-1 ein (ein Android-Client pro SHA-1), oder das Team teilt sich einen Debug-Keystore.

## 4. Den Android-Client anlegen {#_4-create-the-android-client}

**Clients → Create client**:

| Feld                         | Wert                                  |
|------------------------------|---------------------------------------|
| Application type             | **Android**                           |
| Name                         | z. B. `AgentTools debug`              |
| Package name                 | `com.elkuku.agenttools`               |
| SHA-1 certificate fingerprint | der Debug-SHA-1 aus Schritt 3        |

Speichere. Die entstandene Client-ID musst du nicht kopieren. Änderungen können ein paar Minuten
brauchen, bis sie wirken.

**Release-Builds** brauchen einen weiteren Android-Client mit dem SHA-1 des **Release**-Schlüssels.
Wird die App über Google Play mit Play App Signing verteilt, nimm den SHA-1 des
**App-Signaturschlüssels** aus *Play Console → Test and release → App integrity*, nicht deinen
Upload-Schlüssel.

## 5. Beide Projekte konfigurieren {#_5-configure-both-projects}

Verwende an beiden Stellen die **Web**-Client-ID aus Schritt 2.

`web/.env.local` (von git ignoriert):

```dotenv
OAUTH_GOOGLE_ID=1234567890-abc.apps.googleusercontent.com
```

`app/local.properties` (von git ignoriert, siehe `local.properties.example`):

```properties
google.serverClientId=1234567890-abc.apps.googleusercontent.com
server.baseUrl.debug=http://10.0.2.2:8000
```

Baue die App nach einer Änderung an `local.properties` neu, denn der Wert wird in `BuildConfig`
kompiliert.

## 6. Das Benutzerkonto {#_6-the-user-account}

Die Anmeldung mit der App (oder im Web) registriert ein unbekanntes Google-Konto als Benutzer, mit
seiner E-Mail-Adresse als Kennung und `ROLE_USER`. Es speichert sofort Agentenstatistiken und
Sondermedaillen; die ersten Statistiken legen seinen Agentennamen fest. Um dich zum Admin zu
machen, ändere die Rolle in der Benutzerverwaltung (Admin → Agenten → bearbeiten → Rolle), oder lege
den Benutzer vorher an:

```shell
cd web
symfony console user-admin
# → Create User → Identifier: you@gmail.com → Role: ROLE_ADMIN
```

Bei der ersten Anfrage speichert der Server die Google-ID des Kontos bei diesem Benutzer.

## 7. Ausprobieren {#_7-try-it}

```shell
cd web && symfony serve --no-tls --allow-all-ip --port=8000
```

Starte einen Emulator mit einem **Google Play**-System-Image und füge dein Google-Konto unter
*Einstellungen → Passwörter & Konten* hinzu. Installiere dann die App (`./gradlew installDebug`) und
melde dich an. Teile deine All-Time-Statistiken aus Ingress mit der App und speichere sie. Sie
erscheinen unter `http://localhost:8000/agent-stats`.

## Fehlerbehebung {#troubleshooting}

| Symptom                                                                 | Wahrscheinliche Ursache                                                                                                      |
|-------------------------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------|
| Die Anmeldung schlägt sofort fehl („developer console is not set up correctly“, `DEVELOPER_ERROR`, Fehler 10 oder `NoCredentialException`, obwohl ein Konto existiert) | Paketname oder SHA-1 passen zu keinem Android-Client, `serverClientId` ist die Android- statt der Web-Client-ID, oder die Clients sind in verschiedenen Projekten. Warte nach Änderungen ein paar Minuten. |
| „Auf diesem Gerät ist kein Google-Konto verfügbar.“                     | Kein Google-Konto auf dem Gerät, oder das Emulator-Image hat keine Google-Play-Dienste.                                     |
| „Access blocked“ / App nicht überprüft                                  | Der Zustimmungsbildschirm ist im Status Testing und das Konto ist kein Testbenutzer.                                        |
| `google.serverClientId is not set in local.properties.`                 | Fülle `local.properties` aus und baue neu.                                                                                  |
| Speichern oder Laden ergibt `401 Invalid ID token.`                     | `OAUTH_GOOGLE_ID` auf dem Server weicht von `google.serverClientId` der App ab (falsche Zielgruppe), oder die Uhr des Servers geht falsch. |
| Speichern ergibt `422` „This agent name is already taken.“              | Ein anderer Benutzer hat den Agentennamen der Statistiken (ohne Groß-/Kleinschreibung); ein Admin ändert ihn in der Benutzerverwaltung. |
| Speichern oder Laden ergibt `401 Email address is not verified.`        | Die E-Mail-Adresse des Google-Kontos ist nicht bestätigt.                                                                   |
| Netzwerkfehler / Verbindung abgelehnt                                   | Server nicht mit `--allow-all-ip` gestartet, falscher Port oder TLS aktiviert (die Debug-App verwendet einfaches HTTP zu `10.0.2.2`). |
