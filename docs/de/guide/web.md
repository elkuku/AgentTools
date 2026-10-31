# Die Website

[agent-tools.elkuku.dev](https://agent-tools.elkuku.dev) zeigt dasselbe Konto wie die App: deine
Statistiken mit Medaillen, deine Zeitleiste, deine Banner und die anderen Agenten. Die Uploads
kommen aus der App; die Website kann außerdem Statistiken importieren, die du anderswo gespeichert
hast.

## Anmelden {#sign-in}

![Die Anmeldeseite](/screenshots/web/de/login.webp){.shot-web}

Klicke auf **Über Google anmelden** und wähle dasselbe Google-Konto wie in der App. Beim ersten Mal
bringt dich die Website zu deinem Profil, damit du wählst, was andere Benutzer sehen.

Die Navigationsleiste enthält deine Statistiken, **Meine Banner** und **Agenten**. Rechts: dein
Profilbild mit dem Benutzermenü (**Meine Statistiken**, **Meine Zeitleiste**, **Meine Banner**, Profil, **Statistiken importieren**, Abmelden), die Sprache
(Englisch, Deutsch, Spanisch) und das helle oder dunkle Design.

## Startseite {#home}

![Die Startseite](/screenshots/web/de/home.webp){.shot-web}

Die Startseite begrüßt dich, verlinkt deine Agentenstatistiken und hat den Download-Link für die
App.

## Agentenstatistiken {#agent-stats}

![Meine Agentenstatistiken](/screenshots/web/de/my-stats.webp){.shot-web}

**Agentenstatistiken** zeigt deinen neuesten Upload wie die App: Agent, Fraktion und Level,
C.O.R.E-Monate, AP, jede Medaille mit ihrer Stufe, Dronenet und Kinetics, deine Sondermedaillen
und Zusammenfassungen deiner Banner und deiner Zeitleiste.

Klicke auf eine Medaille für ihre Details: die Statistik dahinter, deinen Wert und den Wert jeder
Stufe.

![Die Details einer Medaille](/screenshots/web/de/medal-details.webp){.shot-web}

## Zeitleiste {#timeline}

![Die Zeitleiste](/screenshots/web/de/timeline.webp){.shot-web}

Die Zeitleiste (**Agentenstatistiken → Zeitleiste**) enthält deine Uploads mit dem, was sich
geändert hat, deine Ereignisse und Reisen und deine datierten Banner, das Neueste zuerst und nach Jahren, unter
einem Diagramm deiner AP. Sie funktioniert wie die [Zeitleiste der App](/de/guide/app#timeline):
dieselben Filter und ein Datumsbereich, der das Diagramm zoomt.

**Ereignis hinzufügen** öffnet ein Formular für ein neues Ereignis: seinen Tag, einen optionalen
Endtag für eine Reise und was passiert ist. Die Stift- und Papierkorb-Schaltflächen ändern oder
löschen ein Ereignis; bei einem Banner ändert der Stift den Tag, an dem du es gemacht hast.

![Ein Ereignis hinzufügen](/screenshots/web/de/timeline-add-event.webp){.shot-web}

## Statistiken importieren {#import-stats}

![Statistiken importieren](/screenshots/web/de/import.webp){.shot-web}

Benutzermenü → **Statistiken importieren** importiert Statistiken, die du vor AgentTools
gespeichert hast, z. B. auf der 4e-website (dort: Kontomenü → **Migrate**):

- **Statistiken als CSV** (**Export CSV**): eine Kopfzeile mit den Spaltennamen (`datetime`,
  `nickname`, `faction`, `ap`, …) und eine Zeile pro Upload. Statistiken, die du schon hast, werden
  übersprungen, und nur Statistiken deines Agenten werden importiert.
- **Sondermedaillen als JSON** (**Export custom medals**): mit deinen zusammengeführt; eine
  Medaille, die du schon hast, behält die höhere Stufe.

Die Seite meldet, was sie importiert und übersprungen hat und was sie nicht erkannt hat.

## Meine Banner {#my-banners}

![Meine Banner](/screenshots/web/de/banners.webp){.shot-web}

**Meine Banner** zeigt die Banner, die die App von Bannergress synchronisiert hat (nur die App kann
sie synchronisieren): eine Karte mit einem Punkt, wo jedes Banner beginnt, und die Listen der
erledigten, zu erledigenden und veröffentlichten Banner. Die Schaltflächen unter der Karte blenden
jede Liste ein oder aus. Trage in der Erledigt-Liste den Tag ein, an dem du ein Banner gemacht
hast, um es auf deine Zeitleiste zu setzen.

## Agenten {#agents}

![Die Liste der Agenten](/screenshots/web/de/agents.webp){.shot-web}

**Agenten** listet die Agenten, die ihr Profil zeigen. Klicke auf einen, um die Teile zu sehen, die
er zeigt. Wie bei deinen Agentenstatistiken sind seine Zeitleiste und seine Banner dort
zusammengefasst und haben eigene Seiten.

![Das Profil eines Agenten](/screenshots/web/de/agent-profile.webp){.shot-web}

## Profil {#profile}

![Die Profilseite](/screenshots/web/de/profile.webp){.shot-web}

Das Profil (Benutzermenü → **Profil**) zeigt deine Kennung und deinen Agentennamen und die
Sichtbarkeit jedes Teils deines Profils; siehe [Datenschutz](/de/guide/privacy). Änderungen hier
und in den Einstellungen der App betreffen dieselbe Einstellung.

## Für Admins {#for-admins}

Admins haben das Menü **Admin**:

- **Agenten**: alle Benutzer mit Bild, Agentenname, Rolle, was sie zeigen, und ihrem Level. Admins
  können Benutzer anlegen, bearbeiten und löschen, z. B. um einen Agentennamen zu korrigieren.
- **Über** und **Logs**: die Systeminformationen und das Deploy-Log.
- Die Profilseite zeigt einen API-Token für die Schaltfläche **Authorize** der
  [API-Dokumentation](https://agent-tools.elkuku.dev/api/docs).
