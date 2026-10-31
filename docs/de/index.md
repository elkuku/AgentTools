---
layout: home

hero:
  name: AgentTools
  text: Deine Ingress-Statistiken und Medaillen an einem Ort
  tagline: Teile deine All-Time-Statistiken aus Ingress mit der Android-App. Sie speichert sie, zeigt, was sich geändert hat, und bewahrt deine Medaillen, deine Zeitleiste und deine Banner auf, auf dem Handy und im Web.
  image:
    src: /logo.svg
    alt: AgentTools
  actions:
    - theme: brand
      text: Los geht's
      link: /de/guide/
    - theme: alt
      text: App herunterladen
      link: /de/downloads
    - theme: alt
      text: Website öffnen
      link: https://agent-tools.elkuku.dev

features:
  - icon: 📤
    title: Aus Ingress teilen
    details: Öffne dein Agentenprofil, wähle ALL TIME und teile es mit AgentTools. Die Statistiken werden sofort gespeichert.
    link: /de/guide/app#share-your-stats
  - icon: 📈
    title: Sieh, was sich geändert hat
    details: Nach jedem Upload jede gestiegene Statistik, die Medaillen mit neuer Stufe und dein neues Level.
    link: /de/guide/app#see-what-changed
  - icon: 🏅
    title: Medaillen und Sondermedaillen
    details: Deine Medaillen von Bronze bis Onyx und Anomalie-, Jahres- und Event-Medaillen, aus Screenshots gelesen.
    link: /de/guide/app#custom-medals
  - icon: 🗓️
    title: Zeitleiste
    details: Deine Uploads, Level-Aufstiege und Medaillen-Upgrades mit deinen eigenen Ereignissen und Reisen, mit einem AP-Diagramm.
    link: /de/guide/app#timeline
  - icon: 🗺️
    title: Banner
    details: Synchronisiere deine veröffentlichten, erledigten und noch zu erledigenden Banner von Bannergress und sieh sie auf einer Karte.
    link: /de/guide/app#banners
  - icon: 👥
    title: Agenten
    details: Sieh die Agenten, die ihr Profil teilen, und wähle, was andere von deinem sehen.
    link: /de/guide/privacy
---

<div class="screens">
  <img src="/screenshots/app/home.webp" alt="Der Startbildschirm der App">
  <img src="/screenshots/app/my-stats.webp" alt="Meine Statistiken mit Medaillen">
  <img src="/screenshots/app/import-result.webp" alt="Was sich nach einem Upload geändert hat">
  <img src="/screenshots/app/timeline.webp" alt="Die Zeitleiste">
</div>

<style>
.screens {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  max-width: 1152px;
  margin: 48px auto 0;
  padding: 0 24px;
}
.screens img {
  border-radius: 12px;
  border: 1px solid var(--vp-c-divider);
}
@media (max-width: 768px) {
  .screens { grid-template-columns: repeat(2, 1fr); }
}
</style>
